<?php

namespace Tests\Feature\Api;

use App\Models\Draw;
use App\Models\Hexagram;
use Illuminate\Support\Facades\Storage;
use Tests\ApiTestCase;

/**
 * F7-BE (ADR-002 §2) — GET /s/{token}/og.png: 1200×630 PHP GD, cache file
 * storage/app/share-og/{token}.png render ĐÚNG 1 lần; glyph CJK thiếu → fallback
 * chữ, KHÔNG 500; token lạ → 404 nhẹ.
 */
class ShareOgImageTest extends ApiTestCase
{
    private function tokenFor(string $dev, int $hexagramId = 11, array $changing = [2]): string
    {
        $draw = Draw::query()->create([
            'device_id' => $dev,
            'hexagram_id' => $hexagramId,
            'drawn_date' => self::VN_DATE,
            'lines_rolled' => [7, 8, 9, 6, 7, 8],
            'changing_lines' => $changing,
        ]);

        return $this->asDevice($dev)->postJson('/api/share-links', ['draw_id' => $draw->id])->json('token');
    }

    private function cachePath(string $token): string
    {
        return storage_path('app/share-og/'.$token.'.png');
    }

    public function test_og_png_returns_200_image_png_1200x630(): void
    {
        $dev = $this->deviceId();
        $token = $this->tokenFor($dev);

        $resp = $this->get("/s/{$token}/og.png");
        $resp->assertOk();
        $this->assertSame('image/png', $resp->headers->get('Content-Type'));

        $tmp = tempnam(sys_get_temp_dir(), 'og').'.png';
        file_put_contents($tmp, $resp->getContent());
        $info = getimagesize($tmp);
        unlink($tmp);

        $this->assertSame(1200, $info[0]);
        $this->assertSame(630, $info[1]);
        $this->assertSame(IMAGETYPE_PNG, $info[2]);
    }

    /** Cache: render 1 lần — request 2 trả đúng file đã ghi (mtime không đổi). */
    public function test_render_once_then_cache_hit(): void
    {
        $dev = $this->deviceId();
        $token = $this->tokenFor($dev);

        $this->get("/s/{$token}/og.png")->assertOk();
        $path = $this->cachePath($token);
        $this->assertFileExists($path, 'phải ghi cache storage/app/share-og/{token}.png');
        $mtime1 = filemtime($path);
        $bytes1 = filesize($path);

        sleep(1);
        $resp2 = $this->get("/s/{$token}/og.png")->assertOk();
        clearstatcache();
        $this->assertSame($mtime1, filemtime($path), 'cache hit không được re-render');
        $this->assertSame($bytes1, filesize($path));
        $this->assertSame($bytes1, strlen($resp2->getContent()));
    }

    /** Symbol CJK ䷊ thiếu glyph trên server (DejaVu) → fallback, KHÔNG 500. */
    public function test_missing_cjk_glyph_falls_back_not_500(): void
    {
        $this->markTestSkippedIfNoGd();
        $dev = $this->deviceId();
        $token = $this->tokenFor($dev, 11, []); // TH2 dai_ci + symbol ䷊

        $resp = $this->get("/s/{$token}/og.png");
        $resp->assertOk();
        $this->assertSame('image/png', $resp->headers->get('Content-Type'));
    }

    /** quẻ dai_ci rỗng → thẻ tối giản vẫn render được (E6). */
    public function test_empty_dai_ci_renders_minimal_card(): void
    {
        Hexagram::query()->where('id', 3)->update(['dai_ci' => '']);
        $dev = $this->deviceId();
        $token = $this->tokenFor($dev, 3, []);

        $this->get("/s/{$token}/og.png")->assertOk();
    }

    public function test_unknown_token_404(): void
    {
        $this->get('/s/zzzzzzzzzz/og.png')->assertNotFound();
    }

    /** Vết màu THẺ: nền #F7F2E7 ở góc (0,0) theo token thiết kế. */
    public function test_background_color_is_paper(): void
    {
        $dev = $this->deviceId();
        $token = $this->tokenFor($dev);
        $bin = $this->get("/s/{$token}/og.png")->getContent();

        $im = imagecreatefromstring($bin);
        $rgb = imagecolorsforindex($im, imagecolorat($im, 5, 5));
        imagedestroy($im);

        // dung sai 2 cấp để không vỡ vì PNG filter
        $this->deltaLessThan(abs($rgb['red'] - 0xF7), 3);
        $this->deltaLessThan(abs($rgb['green'] - 0xF2), 3);
        $this->deltaLessThan(abs($rgb['blue'] - 0xE7), 3);
    }

    private function deltaLessThan(int $v, int $max): void
    {
        $this->assertLessThanOrEqual($max, $v);
    }

    private function markTestSkippedIfNoGd(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('PHP GD không có trên binary test');
        }
    }
}
