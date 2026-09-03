<?php

namespace Tests\Unit\Services;

use App\Services\AiBoxClient;
use App\Services\LlmParaphraseJudge;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * QUOTA-N/Q3 (card t_1bb07a82) — judge thật trên transport router: 1 call
 * router-model (temp 0, max_tokens 8, timeout RIÊNG router) xuất đúng
 * DU_GIONG|KHAC|UNCLEAR. Mọi hỏng (mạng/HTTP/content ranh) → UNCLEAR —
 * fail-open theo D4 (lệnh boss): nghiêng về cho hỏi thật, tính lượt.
 *
 * Run ở Unit vì chỉ đụng Http::fake + config, zero DB — cùng phong cách
 * RouterBudgetConfigTest.
 */
class LlmParaphraseJudgeTest extends TestCase
{
    private function judge(): LlmParaphraseJudge
    {
        return new LlmParaphraseJudge(new AiBoxClient);
    }

    /** content provider -> verdict mong đợi (card: mock 4 loại DU_GIONG/KHAC/UNCLEAR/ranh). */
    public static function verdictProvider(): array
    {
        return [
            'DU_GIONG' => ['DU_GIONG', 'DU_GIONG'],
            'khac thuong' => ['khac', 'KHAC'],
            'UNCLEAR' => ['UNCLEAR', 'UNCLEAR'],
            'ranh' => ['', 'UNCLEAR'],
            'rac free-text' => ['tôi nghĩ là DU_GIONG đó', 'UNCLEAR'],
            'co dau phay' => ['DU_GIONG.', 'UNCLEAR'],
        ];
    }

    /** @dataProvider verdictProvider */
    public function test_verdict_theo_content_provider(string $content, string $want): void
    {
        Http::fake(['*chat/completions' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => $content]]],
        ])]);
        $this->assertSame($want, $this->judge()->verdict('Bao giờ tôi đổi được việc?', 'Chuyển việc khi nào thuận'));
    }

    public function test_call_dung_transport_router(): void
    {
        Http::fake(['*chat/completions' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'KHAC']]],
        ])]);
        $this->judge()->verdict('cau cu', 'cau moi');

        Http::assertSent(function (Request $r) {
            $body = json_decode((string) $r->body(), true);
            $this->assertSame((string) config('project.ai.router_model'), $body['model'], 'router-model hiện có, không model luận');
            $this->assertSame(0, $body['temperature'], 'temp 0 — phán quyết không sáng tạo');
            $this->assertSame((int) config('project.ai.router_max_tokens'), $body['max_tokens'], '~8 token, cùng budget router');

            return str_contains($r->url(), '/chat/completions');
        });
    }

    public function test_loi_mang_va_http_deu_unclear_khong_bao_gio_throw(): void
    {
        // 1 fake duy nhat (pitfall merge stubCallbacks) — cbi $fail quyet kieu hong.
        $fail = 'net';
        Http::fake(['*chat/completions' => function () use (&$fail) {
            if ($fail === 'net') {
                throw new ConnectionException('timeout');
            }

            return Http::response(null, 500);
        }]);
        $this->assertSame('UNCLEAR', $this->judge()->verdict('a', 'b'), 'timeout = fail-open UNCLEAR');
        $fail = 'http';
        $this->assertSame('UNCLEAR', $this->judge()->verdict('a', 'b'), 'HTTP 5xx = fail-open UNCLEAR');
    }

    public function test_key_trong_unclear_khong_goi_mang(): void
    {
        config(['aibox.api_key' => '']);
        Http::fake();
        $this->assertSame('UNCLEAR', $this->judge()->verdict('a', 'b'));
        Http::assertNothingSent();
    }

    public function test_khong_co_nguồn_so_sanh_khong_goi_llm(): void
    {
        Http::fake();
        $j = $this->judge();
        $this->assertSame('KHAC', $j->verdict(null, 'b'), 'không bài cũ = không có gì so — hỏi thật');
        $this->assertSame('KHAC', $j->verdict('   ', 'b'));
        $this->assertSame('KHAC', $j->verdict('a', '   '));
        Http::assertNothingSent();
    }

    public function test_is_same_meaning_chi_d_u_gion_g_moi_true(): void
    {
        // 1 fake DUY NHAT cho ca vong (pitfall Be2TestCase: Factory::fake chi
        // merge stubCallbacks → fake lan 2 khong de lan 1). Bien $content dong
        // luc doc tai luc goi.
        $content = null;
        Http::fake(['*chat/completions' => function () use (&$content) {
            return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => $content]]]]);
        }]);
        foreach ([['DU_GIONG', true], ['KHAC', false], ['UNCLEAR', false], ['rác', false]] as [$c, $want]) {
            $content = $c;
            $this->assertSame($want, $this->judge()->isSameMeaning('Bao giờ đổi việc được?', 'Chuyển việc khi nào'), $c);
        }
    }
}
