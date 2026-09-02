<?php

namespace Tests\Unit\Services;

use App\Services\AiBoxClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * BUG-V3-1 (card t_05d92158, QA-LUAN-V3 t_b8a95f0a) — router chết im lặng 100%
 * với API AIBOX THẬT: model reasoning (deepseek-v4-flash) + max_tokens=8 →
 * finish_reason=length, content='' (lý lẽ ăn hết budget, nhãn bị cắt). Stub
 * router-qna-test không reasoning nên che mất bug (bài học: mock ≠ model thật).
 *
 * FIX chốt theo latency (probe THẬT 2026-09-01, evidence/probe_router_fix*.py):
 *  - project.php ai.router_model = 'qwen3.6-flash' (single source): non-reasoning phía
 *    content — 4/4 nhãn NGUYÊN VĂN đúng whitelist ngay mt=8, 3.0–5.8s < timeout 10s.
 *    Đường cũ "router_model rỗng → fallback model luận" chính là cái bẫy: model
 *    luận hiện tại reasoning → router luôn chết. Rỗng giờ về model router mặc định.
 *  - project.php ai.router_max_tokens = 8 GIỮ NGUYÊN, chỉ phát động qua config:
 *    deepseek-v4-flash phải ≥192 token mới nhả nhãn (~2.5s) — tăng budget theo
 *    hướng card (64) KHÔNG đủ cho model reasoning; đổi model là fix đúng.
 *  - BUG-V3-3: bỏ log đếm-mù aibox.router.sent (ghi TRƯỚC parse), thay bằng
 *    aibox.router.result SAU parse chứa GIÁ TRỊ route (label|null|UNCLEAR) + finish.
 */
class RouterBudgetConfigTest extends TestCase
{
    /** @var list<string> dòng log aibox.router.* sinh ra trong call gần nhất */
    private array $routerLogLines = [];

    /** Gọi routeTopic với content giả định; ghi lại log, trả route parse được. */
    private function routeWith(string $content, ?string $finish = 'stop', string $routerModel = ''): ?string
    {
        config([
            'aibox.base_url' => 'https://fake.test/v1',
            'aibox.api_key' => 'k',
            'aibox.model' => 'deepseek-v4-flash',
            'aibox.router_model' => $routerModel, // '' = env không khai báo → đường fallback mới
        ]);
        Http::fake([
            '*chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => $finish]],
            ]),
        ]);

        $route = $this->capture(fn () => (new AiBoxClient)->routeTopic('Bao giờ em có người yêu'));

        return $route;
    }

    /** Chạy fn, trả kết quả; đồng thời gom các dòng log mới chứa 'aibox.router'. */
    private function capture(callable $fn): mixed
    {
        $log = storage_path('logs/laravel.log');
        $off = file_exists($log) ? (int) filesize($log) : 0;
        $out = $fn();
        clearstatcache(true, $log);
        $this->routerLogLines = array_values(array_filter(
            explode("\n", (string) @file_get_contents($log, false, null, $off)),
            fn ($l) => str_contains($l, 'aibox.router')
        ));

        return $out;
    }

    /** Dòng log cuối chứa marker → context đã json_decode; không có → null. */
    private function lastLog(string $marker): ?array
    {
        foreach (array_reverse($this->routerLogLines) as $line) {
            if (! str_contains($line, $marker)) {
                continue;
            }
            $ctxPos = strpos($line, '{', strpos($line, $marker) + strlen($marker));
            $ctx = $ctxPos === false ? [] : (json_decode(substr($line, $ctxPos), true) ?: []);

            return ['context' => $ctx];
        }

        return null;
    }

    /** Single source of truth: 2 giá trị router mới trong project.php, đã probe. */
    public function test_project_config_gia_tri_router(): void
    {
        $this->assertSame('qwen3.6-flash', config('project.ai.router_model'));
        $this->assertSame(8, config('project.ai.router_max_tokens'));
    }

    /**
     * CFG-BE (A6): .env.example khai AIBOX_ROUTER_MODEL RỖNG — default nghiệp vụ
     * non-reasoning nằm project.php; env chỉ là override khẩn. Deploy đọc file
     * vẫn thấy cảnh báo non-reasoning (BUG-V3-1) ngay cạnh dòng khai.
     */
    public function test_env_vi_du_khai_bao_router_model_override(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));
        $this->assertMatchesRegularExpression('/^AIBOX_ROUTER_MODEL=$/m', $example,
            '.env.example: override router để TRỐNG = dùng default project.php (CFG-BE)');
        $this->assertStringContainsString('non-reasoning', $example,
            'cảnh báo BUG-V3-1 phải còn trong .env.example');
        $this->assertSame('qwen3.6-flash', config('project.ai.router_model'),
            'default nghiệp vụ non-reasoning phải ở project.php');
    }

    /** Payload router khi env KHÔNG khai router_model: về default project.php, không fallback model luận. */
    public function test_rong_khong_fallback_model_luan_nua(): void
    {
        $this->assertSame('duyen', $this->routeWith('duyen'));
        Http::assertSent(function (Request $r) {
            $this->assertSame((string) config('project.ai.router_model'), (string) $r['model'],
                'router_model rỗng → default project.php (model luận reasoning là bug, cấm fallback)');
            $this->assertSame(0, $r['temperature']);
            $this->assertSame((int) config('project.ai.router_max_tokens'), $r['max_tokens']);

            return true;
        });
    }

    /** router_model khai báo tường minh vẫn thắng mặc định (escape hatch vận hành). */
    public function test_router_model_tuong_minh_van_thang(): void
    {
        $this->routeWith('duyen', 'stop', 'glm-5.2-fast-preview');
        Http::assertSent(fn (Request $r) => (string) $r['model'] === 'glm-5.2-fast-preview');
    }

    /** BUG-V3-3: log GIÁ TRỊ route sau parse; log đếm-mù trước parse biến mất. */
    public function test_log_gia_tri_route_sau_parse(): void
    {
        $this->routeWith('duyen');
        $this->assertNull($this->lastLog('aibox.router.sent'), 'log đếm-mù trước parse phải bỏ');
        $entry = $this->lastLog('aibox.router.result');
        $this->assertNotNull($entry, 'phải có aibox.router.result sau parse');
        $this->assertSame('duyen', $entry['context']['route']);
    }

    /** Đúng kịch bản BUG-V3-1 (content rỗng + finish=length) → route=null + finish hiện log. */
    public function test_log_route_null_khi_content_rong(): void
    {
        $this->assertNull($this->routeWith('', 'length'));
        $entry = $this->lastLog('aibox.router.result');
        $this->assertNotNull($entry);
        $this->assertArrayHasKey('route', $entry['context'], 'route=null cũng phải thành key trong log');
        $this->assertNull($entry['context']['route']);
        $this->assertSame('length', $entry['context']['finish'] ?? null, 'finish_reason phải lộ để bắt reasoning-cut');
    }

    /** Nhãn rác parse → UNCLEAR, giá trị vẫn phải thấy trong log. */
    public function test_log_unclear_khi_nhan_rac(): void
    {
        $this->assertSame('UNCLEAR', $this->routeWith('có lẽ là duyen đó'));
        $this->assertSame('UNCLEAR', $this->lastLog('aibox.router.result')['context']['route']);
    }

    /** Router HTTP fail: log failed có status (hành vi cũ giữ) + KHÔNG sinh router.sent. */
    public function test_log_khi_router_that_bai(): void
    {
        config([
            'aibox.base_url' => 'https://fake.test/v1', 'aibox.api_key' => 'k',
            'aibox.model' => 'deepseek-v4-flash', 'aibox.router_model' => 'qwen3.6-flash',
        ]);
        Http::fake(['*chat/completions' => Http::response(null, 500)]);
        $this->assertNull($this->capture(fn () => (new AiBoxClient)->routeTopic('abc')));
        $this->assertNotNull($this->lastLog('aibox.router.failed'));
        $this->assertSame(500, $this->lastLog('aibox.router.failed')['context']['status']);
        $this->assertNull($this->lastLog('aibox.router.sent'));
    }
}
