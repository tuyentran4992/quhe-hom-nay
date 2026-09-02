<?php

namespace Tests\Unit\Services;

use App\Services\ShareOgRenderer;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * VS3-S4 (SPEC-VS3 §S4) — cache OG partition theo domain + og_version:
 * cachePath = storage/app/share-og/{version}/{token}.png với
 * version = substr(sha1(app.url|og_version),0,8). Forget dọn MỌI version dir.
 * Unit thuần (không DB): dựng path qua renderer public cachePath().
 */
class ShareOgRendererCacheTest extends TestCase
{
    private ShareOgRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new ShareOgRenderer;
    }

    /** Hai app.url khác nhau (Config::set) → hai cachePath khác nhau. */
    public function test_cache_path_partitions_by_app_url(): void
    {
        Config::set('project.share.og_version', 1);
        Config::set('app.url', 'https://old-tunnel.example');
        $pathA = $this->renderer->cachePath('tok12345678');

        Config::set('app.url', 'https://domain-that.example');
        $pathB = $this->renderer->cachePath('tok12345678');

        $this->assertNotSame($pathA, $pathB, 'đổi domain phải ra thư mục version khác');
        $this->assertStringStartsWith(storage_path('app/share-og/'), $pathA);
        $this->assertMatchesRegularExpression('#/share-og/[0-9a-f]{8}/tok12345678\.png$#', $pathA);
        $this->assertMatchesRegularExpression('#/share-og/[0-9a-f]{8}/tok12345678\.png$#', $pathB);
        // đúng hash công thức SPEC
        $this->assertStringContainsString(
            substr(sha1('https://domain-that.example|1'), 0, 8),
            $pathB
        );
    }

    /** og_version 1→2 đổi cachePath (manual bust khi redesign OG). */
    public function test_cache_path_bumps_with_og_version(): void
    {
        Config::set('app.url', 'https://qhe.example');
        Config::set('project.share.og_version', 1);
        $v1 = $this->renderer->cachePath('tok12345678');

        Config::set('project.share.og_version', 2);
        $v2 = $this->renderer->cachePath('tok12345678');

        $this->assertNotSame($v1, $v2);
    }

    /** Cache hit trong version dir hiện hành: render KHÔNG re-draw (nội dung y nguyên). */
    public function test_render_serves_existing_cache_without_redraw(): void
    {
        Config::set('app.url', 'https://cache-hit.example');
        Config::set('project.share.og_version', 1);
        $path = $this->renderer->cachePath('cachettok01');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        // byte giả: nếu render gọi lại draw() thì nội dung phải ra PNG thật ≠ sentinel
        file_put_contents($path, 'SENTINEL-CACHED-PNG');

        $out = $this->renderer->render('cachettok01', ['card' => [], 'sharer_label' => 'x', 'views' => 0]);

        $this->assertSame('SENTINEL-CACHED-PNG', $out, 'cache hit phải đọc file, không vẽ lại');
        @unlink($path);
        @rmdir(dirname($path));
    }

    /** forget() dọn glob moi-version cua token — token bay cả 2 phiên bản thư mục. */
    public function test_forget_removes_token_across_all_versions(): void
    {
        Config::set('app.url', 'https://forget.example');
        $root = storage_path('app/share-og');
        $paths = [];
        foreach ([1, 2] as $v) {
            Config::set('project.share.og_version', $v);
            $p = $this->renderer->cachePath('forgetme001');
            if (! is_dir(dirname($p))) {
                mkdir(dirname($p), 0775, true);
            }
            file_put_contents($p, 'x');
            $paths[] = $p;
        }
        // 1 file của version hiện hành đã nằm trong $paths — chắc chắn ≥2 dir
        $this->assertGreaterThanOrEqual(2, count(array_unique(array_map('dirname', $paths))));

        $this->renderer->forget('forgetme001');

        foreach ($paths as $p) {
            $this->assertFileDoesNotExist($p, "forget phải xóa mọi version dir: {$p}");
        }
        foreach ($paths as $p) {
            @rmdir(dirname($p));
        }
    }
}
