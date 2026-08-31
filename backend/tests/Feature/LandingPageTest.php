<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * [MKT-F2b] t_1bfee292 — Landing Blade tại GET / (specs/1.mvp/06-mkt-tracking.md §1+§4).
 * TDD RED trước khi code blade. FE chỉ test 2 file mình sở hữu:
 * routes/web.php + resources/views/landing.blade.php. Endpoint #11 /api/track
 * là của be-dev (card song song t_85c2af27) — ở đây chỉ verify JS GỌI ĐÚNG contract,
 * không cần endpoint tồn tại (spec: 404 chấp nhận được lúc dev).
 */
class LandingPageTest extends TestCase
{
    private function getLanding(string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->get('/' . $query);
    }

    // ============ AC1: GET / 200 + khung landing ============

    public function test_root_tra_200_html_landing_khong_con_welcome_laravel(): void
    {
        $res = $this->getLanding()->assertOk();
        $this->assertMatchesRegularExpression('#^text/html; charset=utf-8$#i', (string) $res->headers->get('Content-Type'));
        $html = $res->getContent();
        $this->assertStringContainsString('Hôm nay bạn là quẻ gì?', $html, 'headline sai wording §4');
        $this->assertStringNotContainsString('Laravel', $html, 'welcome.blade cũ còn sót');
        // luận sâu MIỄN PHÍ nổi bật, cấm nhắc 29k (luật anh Tuyền 31/08)
        $this->assertStringContainsString('luận sâu miễn phí', $html);
        $this->assertStringNotContainsString('29k', $html);
        $this->assertStringNotContainsString('29.000', $html);
    }

    // ============ AC2: 2 testid + CTA href ============

    public function test_co_2_testid_bat_buoc(): void
    {
        $html = $this->getLanding()->assertOk()->getContent();
        $this->assertStringContainsString('data-testid="landing-cta-draw"', $html);
        $this->assertStringContainsString('data-testid="landing-link-oa"', $html);
    }

    public function test_cta_mac_dinh_tro_ve_app_khi_khong_utm(): void
    {
        $html = $this->getLanding()->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-testid="landing-cta-draw"[^>]*href="\/app\/?"/', $html);
    }

    public function test_utm_pass_through_vao_href_cta_phi_server(): void
    {
        // §5.4: curl /?utm_campaign=test → HTML trả về CTA href phải chứa utm_campaign=test.
        $html = $this->getLanding('?utm_source=fb&utm_medium=social&utm_campaign=w1')->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/data-testid="landing-cta-draw"[^>]*href="\/app\/\?[^"]*utm_campaign=w1/',
            $html,
            'CTA href không mang utm_campaign khi vào /?utm_campaign=w1'
        );
        foreach (['utm_source=fb', 'utm_medium=social'] as $pair) {
            $this->assertMatchesRegularExpression(
                '/data-testid="landing-cta-draw"[^>]*href="[^"]*' . $pair . '/',
                $html
            );
        }
    }

    public function test_utm_rac_bi_khuc_khong_cut_duoc_thuoc_tinh_html(): void
    {
        // San pham CHUAN HOA trang thai (khong phai chan cat): gia tri rac sau khi
        // sanitize + rawurlencode KHONG duoc chua ky tu pha href attribute
        // (" space > =) — bo lot cua ng + escape Blade la 2 lop phong.
        $evil = urlencode('x" onmouseover="alert(1)"');
        $html = $this->getLanding('?utm_campaign=' . $evil)->assertOk()->getContent();
        preg_match('/data-testid="landing-cta-draw"[^>]*href="([^"]*)"/', $html, $m);
        $this->assertNotEmpty($m, 'khong lay duoc CTA href');
        // '=" va > trong ATTR VALUE moi la chir dau injection; = cua chinh query la hop le.
        $this->assertSame(0, preg_match('/["\'<>]|\s|javascript:|(?:%22|%27|%3C|%3E)/i', $m[1]), 'href chua ky tu nguy hiem: ' . $m[1]);
    }

    // ============ OA env + fallback ============

    public function test_oa_href_doc_tu_env(): void
    {
        config(['landing.oa_url' => 'https://zalo.me/quhe-hom-nay']);
        $html = $this->getLanding()->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-testid="landing-link-oa"[^>]*href="https:\/\/zalo\.me\/quhe-hom-nay"/', $html);
    }

    public function test_oa_fallback_dau_cham_khi_env_trong(): void
    {
        config(['landing.oa_url' => '']);
        $html = $this->getLanding()->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-testid="landing-link-oa"[^>]*href="#"/', $html);
    }

    // ============ GA4 render theo env (§1) ============

    public function test_khong_gtag_khi_ga4_trong(): void
    {
        config(['landing.ga4_measurement_id' => '']);
        $html = $this->getLanding()->assertOk()->getContent();
        $this->assertStringNotContainsString('googletagmanager.com', $html);
        $this->assertStringNotContainsString('gtag(', $html);
    }

    public function test_gtag_render_khi_ga4_co_gia_tri_va_push_2_event(): void
    {
        config(['landing.ga4_measurement_id' => 'G-TESTABC123']);
        $html = $this->getLanding()->assertOk()->getContent();
        $this->assertStringContainsString('googletagmanager.com/gtag/js?id=G-TESTABC123', $html);
        $this->assertStringContainsString("'landing_visit'", $html);
        $this->assertStringContainsString("'cta_gieo_que'", $html);
    }

    // ============ JS inline + contract /api/track (§1.1) ============

    public function test_js_inline_nho_hon_2kb_va_goi_dung_contract(): void
    {
        $html = $this->getLanding()->assertOk()->getContent();
        preg_match_all('#<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>#s', $html, $m);
        $this->assertNotEmpty($m[1], 'khong co <script> inline');
        $inline = implode("\n", $m[1]);
        $this->assertLessThan(2048, strlen($inline), 'JS inline >= 2KB (§4)');
        $this->assertStringContainsString('/api/track', $inline);
        $this->assertStringContainsString("'landing_visit'", $inline);
        $this->assertStringContainsString('utm_', $inline); // parse location.search (§1.1)
        $this->assertStringContainsString('location.search', $inline);
        $this->assertStringContainsString('credentials', $inline); // same-origin
    }

    // ============ Disclaimer + từ cấm (§4, 04-ui §5) ============

    public function test_footer_disclaimer_dung_tung_chu(): void
    {
        $html = $this->getLanding()->assertOk()->getContent();
        $this->assertStringContainsString('Sản phẩm giải trí, tham khảo văn hoá — không phải nghiên cứu hay tư vấn số mệnh.', $html);
    }

    public function test_khong_lo_vi_pham_tu_cam_04_ui(): void
    {
        // grep theo §5 — loai tru chuoi hop phap nhat vi "giải trí" chua "giải", "tham khảo"...
        $html = $this->getLanding()->assertOk()->getContent();
        foreach (['hóa giải', 'cúng', 'bùa', 'thay đổi vận mệnh', 'mở cung', 'đồng tiền âm phủ'] as $ban) {
            $this->assertStringNotContainsString($ban, $html);
        }
        // "giải hạn" / "tâm linh" / "thỉnh" / "cốt": kiem tra bang regex ranh gioi tu
        $this->assertDoesntMatch('/\b(giải hạn|tâm linh|thỉnh|cốt)\b/u', $html);
    }

    // ============ SEO (§4) ============

    public function test_seo_title_meta_robots(): void
    {
        $html = $this->getLanding()->assertOk()->getContent();
        $this->assertStringContainsString('<title>Quẻ Hôm Nay — gieo quẻ Kinh Dịch miễn phí</title>', $html);
        $this->assertMatchesRegularExpression('/<meta name="description" content="[^"]{40,}"/', $html);
        $this->assertMatchesRegularExpression('/<meta name="robots" content="[^"]*index/i', $html);
    }

    // ============ Khong pha /app/ SPA fallback ============

    public function test_landing_khong_che_spa_fallback(): void
    {
        // [MKT-F2-D1 t_2c84e0c8] Assertion cu `assertStatus(404)` chi dung tren worktree
        // CHUA build SPA; co build that (CI/production serve backend/public/app) -> spa.php
        // tra index.html 200 -> test cu FAIL tren ghep BE+FE cua QA t_74912356 CHECK 5.
        // Bat bien can giu khong doi: '/app/*' do spa.php phuc vu, landing Blade KHONG
        // chen lang mach (khong chuoi 'landing-cta-draw'), khong 500.
        $res = $this->get('/app/que/82');
        if (is_readable(public_path('app/index.html'))) {
            // Moi truong CO build: SPA index phuc vu deep-link — 200 + asset SPA, khong landing.
            $html = $res->assertOk()->getContent();
            $this->assertStringNotContainsString('landing-cta-draw', $html, 'landing chen lang mach /app/*');
            $this->assertStringContainsString('/app/assets', $html, '/app/* khong do SPA index phuc vu');
        } else {
            // Moi truong CHUA build: spa.php abort(404) — van khong 500, van khong landing.
            $res->assertStatus(404);
            $this->assertStringNotContainsString('landing-cta-draw', $res->getContent());
        }
        // Bao phu CAI CON LAI ngay trong lan chay nay (khong phu thuoc state build cua CI):
        $this->verifySpaFallbackBothStates();
    }

    /**
     * Du bao build that cua CI, dao 2 trang thai index.html co/khong trong cung mot lan
     * chay (cung kỹ thuật backup/restore index SPA như SpaFallbackTest — file that khong bao gio mat).
     */
    private function verifySpaFallbackBothStates(): void
    {
        $index = public_path('app/index.html');
        $backup = is_readable($index) ? file_get_contents($index) : null;
        try {
            if ($backup === null) {
                @mkdir(dirname($index), 0777, true);
            }
            file_put_contents($index, '<!doctype html><html><body><div id="app">MKT-F2-D1-SPA</div><script src="/app/assets/probe.js"></script></body></html>');
            $html = $this->get('/app/que/82')->assertOk()->getContent();
            $this->assertStringNotContainsString('landing-cta-draw', $html, 'landing chen lang mach /app/* khi co build');
            $this->assertStringContainsString('/app/assets', $html, 'SPA index khong serve asset path');

            unlink($index);
            $res = $this->get('/app/que/82')->assertStatus(404);
            $this->assertStringNotContainsString('landing-cta-draw', $res->getContent(), 'landing chen lang mach /app/* khi chua build');
        } finally {
            if ($backup !== null) {
                file_put_contents($index, $backup);
            } elseif (is_readable($index) && str_contains((string) file_get_contents($index), 'MKT-F2-D1-SPA')) {
                @unlink($index); // chinh em tao thi don, khong dong build cua nguoi khac
            }
        }
        if ($backup !== null) {
            $this->assertSame($backup, file_get_contents($index), 'build SPA that phai con nguyen ven sau test');
        }
    }

    // helper: PHPUnit khong co assertDoesntMatch — mo rong bang preg cho tu cam co ranh gioi.
    protected function assertDoesntMatch(string $pattern, string $haystack): void
    {
        $this->assertSame(0, preg_match($pattern, $haystack), "chuoi cam xuat hien: {$pattern}");
    }
}
