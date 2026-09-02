<?php

namespace Tests\Feature\Api;

use App\Jobs\RunAiBoxJob;
use App\Models\AiJob;
use App\Models\Device;
use App\Services\AiBoxClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * BE-2 — worker RunAiBoxJob + AiBoxClient. Queue database (Be2TestCase) nên
 * dispatch CHỈ ghi pending; handle() gọi thủ công = đúng những gì worker thật chạy.
 * 05-testplan: F8 done flow, fallback AI lỗi/timeout, AI_FILTERED (E4).
 */
class AiWorkerTest extends Be2TestCase
{
    private function worker(int $aiJobId): void
    {
        // container resolve client y như queue worker serialize/unserialize
        (new RunAiBoxJob($aiJobId))->handle(app(AiBoxClient::class));
    }

    private function queuedJob(): AiJob
    {
        $d = $this->device();
        $this->payUnlock($d, 'duyen');
        $draw = $this->drawFor($d);
        $res = $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'wq-'.Str::random(12),
        ])->assertStatus(202);

        return AiJob::query()->where('job_uuid', $res->json('data.job_uuid'))->firstOrFail();
    }

    public function test_worker_done_ghi_result_va_so_200_o_poll(): void
    {
        $job = $this->queuedJob();
        $this->fakeAi($this->cleanMd);

        $this->worker($job->id);

        $job->refresh();
        $this->assertSame(AiJob::ST_DONE, $job->status);
        $this->assertSame($this->cleanMd, $job->result);
        $this->assertNotNull($job->finished_at);

        $d = Device::query()->findOrFail($job->device_id);
        $this->cookieFor($d)->getJson('/api/ai/jobs/'.$job->job_uuid)
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.result', $this->cleanMd);
    }

    public function test_prompt_chua_du_lieu_que_va_khong_lo_secret(): void
    {
        $job = $this->queuedJob();
        $this->fakeAi($this->cleanMd);

        $this->worker($job->id);

        Http::assertSent(function ($req) {
            $sys = $req['messages'][0]['content'];
            $usr = $req['messages'][1]['content'];
            return str_contains($usr, 'Chủ đề luận sâu: tình duyên') // topic duyen map nhãn C-02
                && str_contains($usr, 'Quẻ gốc')
                && str_contains($usr, 'Hào động') // draw có changing_lines [2,4]
                && str_contains($sys, 'giải trí')
                && ! str_contains(json_encode($req->data()), 'sk-'); // key chỉ ở header
        });
    }

    public function test_ai_loc_noi_dung_cam_AI_FILTERED_khong_luu_bai_ban(): void
    {
        // FIX-LUAN-SAU 02/09: 1 lượt dính chữ cấm còn được regenerate — bất biến E4
        // là bài bẩn KHÔNG BAO GIỜ lưu, nên test phải cho model dính CẢ lượt regen.
        $job = $this->queuedJob();
        $this->fakeAi('Mua bùa đổi vận ngay, thầy cam kết linh nghiệm 100%.');
        $this->fakeAi('Vẫn còn bùa: hứa đổi vận tuyệt đối.');

        $this->worker($job->id);

        $job->refresh();
        $this->assertSame(AiJob::ST_FAILED, $job->status);
        $this->assertSame('AI_FILTERED', $job->error_code);
        $this->assertNull($job->result, 'nội dung cấm không được lưu để cache/anti leak');
        Http::assertSentCount(2); // 1 lượt đầu + 1 regenerate, không hơn
    }

    public function test_dinh_chu_cam_duoc_tu_sinh_lai_khong_can_user_bam_thu_lai(): void
    {
        // FIX-LUAN-SAU 02/09 (OBS-FILTER): "cốt lõi" dính regex \bc[oố]t\b — trước
        // đây fail thẳng AI_FILTERED (~1/5 call). Nay worker tự regenerate với
        // feedback nêu đúng chữ phạm → user chỉ thấy bài sạch, không "bàn cờ im tiếng".
        $job = $this->queuedJob();
        $this->fakeAi('Cốt lõi của quẻ là biết dừng đúng lúc.');
        $this->fakeAi($this->cleanMd);

        $this->worker($job->id);

        $job->refresh();
        $this->assertSame(AiJob::ST_DONE, $job->status);
        $this->assertSame($this->cleanMd, $job->result);
        Http::assertSentCount(2);
        Http::assertSent(function ($req) {
            $msgs = $req['messages'] ?? [];

            return count($msgs) === 4
                && ($msgs[2]['role'] ?? '') === 'assistant'
                && str_contains($msgs[2]['content'], 'Cốt lõi')
                && ($msgs[3]['role'] ?? '') === 'user'
                && str_contains($msgs[3]['content'], 'chữ cấm: cốt');
        });
    }

    public function test_luot_dau_cham_hon_budget_khong_regen_fail_thang(): void
    {
        // FIX-LUAN-SAU acceptance #3: lượt đầu đã vượt ngân sách (45s thật) thì THÀ
        // fail sớm cho user bấm thử lại — regeneration vẫn còn lượt nhưng hết cửa
        // thời gian. Mock ngân sách = -1s: ngay sau lượt 1, hiệu thời gian chắc
        // chắn > budget (deterministic, không đợi clock).
        config(['aibox.filter_regen_budget_s' => -1]);
        $job = $this->queuedJob();
        $this->fakeAi('Cốt lõi của quẻ là biết dừng đúng lúc.');
        $this->fakeAi($this->cleanMd); // nếu regen sai nhịp, lượt 2 sẽ sạch → test đỏ

        $this->worker($job->id);

        $job->refresh();
        $this->assertSame(AiJob::ST_FAILED, $job->status);
        $this->assertSame('AI_FILTERED', $job->error_code);
        $this->assertNull($job->result);
        Http::assertSentCount(1); // KHÔNG regenerate
    }

    public function test_ba_loi_upstream_lan_luot_cho_den_khi_can_luot_thanhd_failed(): void
    {
        $job = $this->queuedJob();
        for ($i = 0; $i < 3; $i++) {
            $this->fakeAiStatus(500); // mỗi attempt một response 500 — FIFO đúng thứ tự retry
        }

        for ($i = 1; $i <= 3; $i++) {
            try {
                $this->worker($job->id);
            } catch (\Throwable) {
                // job re-queue để worker kế thử — đúng contract queue
            }
            $job->refresh();
            if ($i < 3) {
                $this->assertSame(AiJob::ST_QUEUED, $job->status, "lượt $i phải còn queued để retry");
            }
        }
        $this->assertSame(AiJob::ST_FAILED, $job->status);
        $this->assertSame('AI_UPSTREAM', $job->error_code);
        Http::assertSentCount(3); // C-04: đúng 3 lần gọi, không hơn
    }

    public function test_khong_timeout_lan_cuoi_thanhd_AI_TIMEOUT(): void
    {
        $job = $this->queuedJob();
        for ($i = 0; $i < 3; $i++) {
            $this->fakeAiThrow(new ConnectionException('cURL error 28: Operation timed out'));
        }

        for ($i = 0; $i < 3; $i++) {
            try {
                $this->worker($job->id);
            } catch (\Throwable) {
            }
        }

        $job->refresh();
        $this->assertSame(AiJob::ST_FAILED, $job->status);
        $this->assertSame('AI_TIMEOUT', $job->error_code);
    }

    public function test_job_da_failed_khong_bao_gio_chuyen_nguoc(): void
    {
        $job = $this->queuedJob();
        $this->fakeAi($this->cleanMd);
        $this->worker($job->id);
        $job->refresh();
        $this->assertSame(AiJob::ST_DONE, $job->status);

        $this->expectException(\RuntimeException::class);
        $job->transitTo(AiJob::ST_RUNNING); // done → running = đi ngược, cấm
    }
}
