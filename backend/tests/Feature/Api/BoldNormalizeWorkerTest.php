<?php

namespace Tests\Feature\Api;

use App\Jobs\RunAiBoxJob;
use App\Models\AiJob;
use App\Services\AiBoxClient;
use Illuminate\Support\Str;

/**
 * BUG-V3-4 (card t_fc8a8953) — worker phai normalize `**` MOT CHO truoc khi luu
 * result (hop dong BUG-V3-2 voi FE luanRender.js: FE chi marker + `#`, `**` la quyen BE).
 * Chung minh QA: DB_162/DB_50/DB_51 bai THAT van luu nguyen van `**` →
 * TopicGate (whitespace-pre-wrap) hien thang `**` cho khach.
 */
class BoldNormalizeWorkerTest extends Be2TestCase
{
    private const DB162_PATH = '/data/agents/qa-engineer/outbox/t_dcc84365/evidence/t5_db/DB_162_c1-duyen-khop.txt';

    private function queuedJob(): AiJob
    {
        $d = $this->device();
        $this->payUnlock($d, 'duyen');
        $draw = $this->drawFor($d);
        $res = $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'bq-' . Str::random(12),
        ])->assertStatus(202);

        return AiJob::query()->where('job_uuid', $res->json('data.job_uuid'))->firstOrFail();
    }

    private function worker(int $aiJobId): void
    {
        (new RunAiBoxJob($aiJobId))->handle(app(AiBoxClient::class));
    }

    public function test_worker_luut_result_khong_con_cap_bold_nhu_db162(): void
    {
        if (!is_file(self::DB162_PATH)) {
            $this->markTestSkipped('thieu evidence QA t_dcc84365 tren may chay test');
        }
        $fixture = file_get_contents(self::DB162_PATH);
        $this->assertStringContainsString('**hãy chủ động', $fixture, 'fixture phai chua cap `**` nhu evidence');

        $job = $this->queuedJob();
        $this->fakeAi($fixture);

        $this->worker($job->id);

        $job->refresh();
        $this->assertSame(AiJob::ST_DONE, $job->status);
        $this->assertStringNotContainsString('**', $job->result);
        // CẤM mất chữ: mọi nội dung thường của fixture còn nguyên (chỉ trừ `*`/newline)
        $strip = static fn (string $s) => str_replace(['*', "\n"], '', $s);
        $this->assertSame($strip($fixture), $strip($job->result));
        // italic đơn cuối bài KHÔNG phải mục tiêu của normalize — giữ nguyên
        $this->assertStringContainsString("*Chỉ mang tính tham khảo giải trí về văn hoá.*", $job->result);
    }
}
