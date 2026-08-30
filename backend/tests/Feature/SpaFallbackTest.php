<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * BUGFIX t_a0d9ee0f D3 — deep-link /app/que/<id> trả Laravel 404 vì SPA fallback
 * chưa tồn tại (Vite dev 5173 có historyApiFallback nên dev không thấy).
 * Spec 04-ui S3: user F5/chia sẻ link PHẢI vào được → server build phải serve
 * public/app/index.html cho MỌI đường dẫn /app/*.
 */
class SpaFallbackTest extends TestCase
{
    private ?string $backup = null;

    private function spaIndexPath(): string
    {
        return public_path('app/index.html');
    }

    protected function setUp(): void
    {
        parent::setUp();
        // index.html THẬT (build output, gitignored) không được mất vì test:
        // backup -> restore mỗi test dù pass/fail.
        $real = $this->spaIndexPath();
        if (is_readable($real)) {
            $this->backup = file_get_contents($real);
        }
    }

    protected function tearDown(): void
    {
        $real = $this->spaIndexPath();
        if ($this->backup !== null) {
            file_put_contents($real, $this->backup);
        } elseif (is_readable($real) && str_contains((string) file_get_contents($real), 'SPA-A0D9')) {
            @unlink($real); // chính em tạo thì dọn, không đụng file khác
        }
        parent::tearDown();
    }

    private function writeSpaIndex(): void
    {
        $dir = public_path('app');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($this->spaIndexPath(), '<!doctype html><html><body><div id="app">SPA-A0D9</div></body></html>');
    }

    private function removeSpaIndex(): void
    {
        @unlink($this->spaIndexPath()); // backup trong setUp phục hồi ở tearDown
    }

    public function test_deep_link_que_id_tra_200_va_html_spa(): void
    {
        $this->writeSpaIndex();
        try {
            $res = $this->get('/app/que/82')->assertOk();
            $res->assertHeader('Content-Type', 'text/html; charset=UTF-8');
            $this->assertStringContainsString('SPA-A0D9', $res->getContent());
        } finally {
            $this->removeSpaIndex();
        }
    }

    public function test_rac_5_route_spa_khac_deep_link(): void
    {
        $this->writeSpaIndex();
        try {
            foreach (['/app/', '/app/draw', '/app/mo-khoa/duyen', '/app/cua-ban', '/app/ky-la-khac/sau/nua'] as $path) {
                $this->get($path)->assertOk();
            }
        } finally {
            $this->removeSpaIndex();
        }
    }

    public function test_fallback_tra_no_store_de_sau_lan_deploy_khong_nhan_html_cu(): void
    {
        // Deep-link HTML không được cache: sau deploy (asset hash đổi), index cũ
        // trong cache trình duyệt sẽ đòi asset 404 → fallback phải no-store.
        $this->writeSpaIndex();
        try {
            $res = $this->get('/app/que/82')->assertOk();
            $this->assertMatchesRegularExpression(
                '/no-(store|cache)/',
                (string) $res->headers->get('Cache-Control'),
                'fallback SPA phải có Cache-Control no-store/no-cache'
            );
        } finally {
            $this->removeSpaIndex();
        }
    }

    public function test_404_khi_spa_chua_build_khong_phai_500(): void
    {
        // index.html vắng (chưa npm build) → fallback không được crash; 404 là đúng.
        $this->removeSpaIndex();
        $this->get('/app/que/82')->assertNotFound();
    }

    public function test_api_404_khong_bi_fallback_nuot(): void
    {
        $this->writeSpaIndex();
        try {
            $this->getJson('/api/webhooks/khong-ton-tai')->assertNotFound();
        } finally {
            $this->removeSpaIndex();
        }
    }
}
