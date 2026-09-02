<?php

namespace Tests\Feature\Api;

use App\Models\Draw;
use App\Models\ShareLink;
use App\Services\ShareOgRenderer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\ApiTestCase;

/**
 * VS3-S3 + VS3-S4 (SPEC-VS3 §S3/§S4, card t_3b27cbde) —
 * S3: chữ "Hôm nay" stale trên /s/ (thẻ hôm qua → ngày đầy đủ dd/MM/yyyy,
 *     thẻ hôm nay → vẫn "Hôm nay d/m"); ngưỡng "N lượt xem" về
 *     config('project.share.views_min') — blade đọc KHÔNG fallback ngầm,
 *     test dựng key bằng Config::set (project.php block `share` do FA-VS3-CONFIG ghép).
 * S4: OG cache partition theo sha1(app.url|og_version) — HTTP smoke 200 image/png.
 * Bất biến: qr_text, drawn_date d/m, disclaimer, testid share-page-cta — zero-diff.
 */
class ShareStaleDateViewsTest extends ApiTestCase
{
    /** Draw theo ngày VN cố định + tạo share-link, trả token. */
    private function tokenFor(string $dev, string $drawnDate): string
    {
        $draw = Draw::query()->create([
            'device_id' => $dev,
            'hexagram_id' => 11,
            'drawn_date' => $drawnDate,
            'lines_rolled' => [7, 8, 9, 6, 7, 8],
            'changing_lines' => [2, 4],
        ]);

        return $this->asDevice($dev)->postJson('/api/share-links', ['draw_id' => $draw->id])->json('token');
    }

    /** Body <main> — tách khỏi <head> (og:site_name "Quẻ Hôm Nay" luôn chứa chữ Hôm Nay). */
    private function mainBody(string $html): string
    {
        $start = strpos($html, '<main');
        $this->assertNotFalse($start, 'phải có <main id="share-card">');

        return substr($html, $start);
    }

    // ================= S3 — stale date =================

    /** Thẻ gieo NGÀY HÔM QUA: hiện dd/MM/yyyy đầy đủ, thân trang hết chữ "Hôm nay". */
    public function test_yesterday_card_shows_full_date_not_hom_nay(): void
    {
        // Carbon::setTestNow = 2026-08-30 02:15 UTC (setUp) → hôm qua = 29/08
        $dev = $this->deviceId();
        $token = $this->tokenFor($dev, '2026-08-29');

        $html = $this->mainBody($this->asDevice(null)->get("/s/{$token}")->getContent());

        $this->assertStringContainsString('29/08/2026', $html, 'phải thấy ngày đầy đủ dd/MM/yyyy');
        $this->assertStringNotContainsString('Hôm nay', $html, 'thẻ hôm qua CẤM còn chữ "Hôm nay" (G3 stale)');
        // bất biến zero-diff (qr_text không escape — so trực tiếp, json_encode escape slash)
        $card = $this->asDevice(null)->getJson("/api/share-links/{$token}")->json('card');
        $this->assertSame('/s/'.$token, $card['qr_text'], 'qr_text bất biến');
        $this->assertSame('29/08', $card['drawn_date'], 'drawn_date d/m bất biến');
        $this->assertFalse($card['is_today']);
    }

    /** Thẻ gieo HÔM NAY: vẫn "Hôm nay d/m" như thiết kế cũ. */
    public function test_today_card_keeps_hom_nay_short_date(): void
    {
        $dev = $this->deviceId();
        $token = $this->tokenFor($dev, self::VN_DATE); // 2026-08-30 = "hôm nay" của fixture

        $html = $this->mainBody($this->asDevice(null)->get("/s/{$token}")->getContent());

        $this->assertStringContainsString('Hôm nay 30/08', $html);
        $this->assertStringNotContainsString('30/08/2026', $html, 'thẻ hôm nay không lộ năm');
        // dòng from :79 — nhánh hôm nay giữ nguyên wording hiện hữu
        $this->assertStringContainsString('Quẻ của', $html);
        $this->assertStringContainsString('hôm nay. Còn bạn?', $html);
    }

    /** Edge rạng sáng (SPEC §Dự phòng): 23:59 hôm trước vs 00:01 hôm sau vẫn đúng nhánh. */
    public function test_is_today_uses_carbon_day_not_string(): void
    {
        $dev = $this->deviceId();
        // drawn hôm qua, xem lúc 00:01 sáng nay → KHÔNG phải same-day
        $token = $this->tokenFor($dev, '2026-08-29');
        Carbon::setTestNow('2026-08-30 00:01:00');

        $card = $this->asDevice(null)->getJson("/api/share-links/{$token}")->json('card');
        $this->assertFalse($card['is_today'], 'so bằng isSameDay Carbon, không so string');

        $today = $this->tokenFor($dev, '2026-08-30');
        $card2 = $this->asDevice(null)->getJson("/api/share-links/{$today}")->json('card');
        $this->assertTrue($card2['is_today']);
    }

    /** buildCardPayload: THÊM 2 khóa dẫn xuất, các khóa cũ zero-diff đúng thứ tự. */
    public function test_card_payload_shape_new_keys_old_unchanged(): void
    {
        $dev = $this->deviceId();
        $token = $this->tokenFor($dev, self::VN_DATE);

        $card = $this->asDevice(null)->getJson("/api/share-links/{$token}")->json('card');

        // 10 khóa, thứ tự: 8 cũ + is_today/drawn_date_full nối tiếp drawn_date
        $this->assertSame(
            ['hexagram_id', 'symbol', 'ten', 'drawn_date', 'is_today', 'drawn_date_full', 'hook', 'keywords', 'disclaimer', 'qr_text'],
            array_keys($card)
        );
        // bất biến PNG/OG khung (proposal §2): các giá trị cũ không đổi format
        $this->assertMatchesRegularExpression('#^\d{2}/\d{2}$#', $card['drawn_date']);
        $this->assertSame('30/08', $card['drawn_date']);
        $this->assertSame('30/08/2026', $card['drawn_date_full']);
        $this->assertIsBool($card['is_today']);
        $this->assertSame('/s/'.$token, $card['qr_text']);
        $this->assertSame('Giải trí · tham khảo văn hoá', $card['disclaimer']);
    }

    // ================= S3 — ngưỡng views về config =================

    /** views=0 → ẩn (test cũ phải giữ PASS). */
    public function test_views_zero_hidden(): void
    {
        Config::set('project.share.views_min', 3);
        $dev = $this->deviceId();
        $token = $this->tokenFor($dev, self::VN_DATE);

        $html = $this->mainBody($this->asDevice(null)->get("/s/{$token}")->getContent());
        // lượt mở trên là device lạ đầu tiên → views=1 (<3) và views=0 baseline
        $this->assertStringNotContainsString('lượt xem', $html);
    }

    /** views=2 < ngưỡng 3 → ẩn; views=3 → hiện "3 lượt xem thẻ này". */
    public function test_views_threshold_three_hides_two_shows_three(): void
    {
        Config::set('project.share.views_min', 3);
        $sharer = $this->deviceId();
        $token = $this->tokenFor($sharer, self::VN_DATE);

        $a = $this->deviceId();
        $b = $this->deviceId();
        $this->asDevice($a)->get("/s/{$token}")->assertOk();   // views=1
        $this->asDevice($b)->get("/s/{$token}")->assertOk();   // views=2
        $this->assertSame(2, (int) ShareLink::query()->where('token', $token)->value('views'));
        $html = $this->mainBody($this->asDevice($a)->get("/s/{$token}")->getContent()); // không +views
        $this->assertStringNotContainsString('lượt xem', $html, 'views=2 dưới ngưỡng 3 phải ẩn');

        $c = $this->deviceId();
        $this->asDevice($c)->get("/s/{$token}")->assertOk();   // views=3
        $this->assertSame(3, (int) ShareLink::query()->where('token', $token)->value('views'));
        $html3 = $this->mainBody($this->asDevice($a)->get("/s/{$token}")->getContent());
        $this->assertStringContainsString('3 lượt xem thẻ này', $html3);
        // số định dạng sẵn có không đổi
        $this->assertStringNotContainsString('2 lượt xem', $html3);
    }

    /** Đổi ngưỡng trong test = đổi hành vi, 0 sửa code (tinh thần QA-CONFIG). */
    public function test_threshold_is_config_driven(): void
    {
        $sharer = $this->deviceId();
        $token = $this->tokenFor($sharer, self::VN_DATE);
        $this->asDevice($this->deviceId())->get("/s/{$token}")->assertOk(); // views=1
        $this->asDevice($this->deviceId())->get("/s/{$token}")->assertOk(); // views=2

        Config::set('project.share.views_min', 1);
        $html = $this->mainBody($this->asDevice($sharer)->get("/s/{$token}")->getContent());
        $this->assertStringContainsString('2 lượt xem thẻ này', $html, 'ngưỡng=1 → views=2 phải hiện');

        Config::set('project.share.views_min', 5);
        $html5 = $this->mainBody($this->asDevice($sharer)->get("/s/{$token}")->getContent());
        $this->assertStringNotContainsString('lượt xem', $html5, 'ngưỡng=5 → views=2 ẩn trở lại');
    }

    // ================= S4 — OG cache partition + HTTP smoke =================

    /** GET /s/{token}/og.png vẫn 200 image/png với cache layout version-dir mới. */
    public function test_og_png_endpoint_200_with_versioned_cache(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('PHP GD không có trên binary test');
        }
        // domain riêng cho test → version dir riêng, không đụng cache test khác
        Config::set('app.url', 'http://vs3-og.test');
        Config::set('project.share.og_version', 1);

        $dev = $this->deviceId();
        $token = $this->tokenFor($dev, self::VN_DATE);

        $resp = $this->get("/s/{$token}/og.png");
        $resp->assertOk();
        $this->assertSame('image/png', $resp->headers->get('Content-Type'));

        $versioned = (new ShareOgRenderer)->cachePath($token);
        $this->assertFileExists($versioned, 'og.png phải ghi vào version dir theo sha1(app.url|og_version)');
        $this->assertMatchesRegularExpression('#/share-og/[0-9a-f]{8}/'.$token.'\.png$#', $versioned);
        // path cũ flat phải KHÔNG còn được dùng
        $this->assertFileDoesNotExist(storage_path('app/share-og/'.$token.'.png'));

        // dọn artifact
        (new ShareOgRenderer)->forget($token);
        @rmdir(dirname($versioned));
    }

    /** Token rác → 404 nhẹ giữ nguyên hành vi E3. */
    public function test_og_png_unknown_token_still_light_404(): void
    {
        $this->get('/s/zzzzzzzzzz/og.png')->assertNotFound();
    }
}
