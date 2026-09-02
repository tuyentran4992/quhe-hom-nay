<?php

namespace Tests\Feature\Api;

use App\Jobs\RunAiBoxJob;
use App\Models\AiJob;
use App\Services\AiBoxClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * BUG-QHN-100 (QA t_00c3fb07) — zombie `running` khi worker chết giữa job.
 *
 * Bệnh cũ: claim UPDATE WHERE status=queued; worker SIGKILL giữa chứng → ai_jobs
 * còn 'running' nhưng row jobs bị redelivery claim-fail + return IM LẶNG → Laravel
 * xoá row jobs → không còn gì tái sinh → API #6 trả 'running' vĩnh viễn, FE poll
 * tới timeout 130s lặp vô hạn.
 *
 * Hợp đồng mới (phương án 2, claim atomic mở cho xác zombie):
 *  - running quá RunAiBoxJob::zombieAfterSeconds() (timeout project.php + dư)
 *    ⇒ lần claim sau ĐÒI LẠI được, chạy đủ như worker thường.
 *  - running CÒN SỐT (worker khác còn sống) ⇒ KHÔNG cướp — ném exception cho queue
 *    redeliver sau retry_after; row jobs được GIỮ LẠI (đường về của zombie).
 *  - done/failed ⇒ return im lặng như cũ (AC-2 cache-hit, không làm lại).
 */
class ZombieReclaimTest extends Be2TestCase
{
    private function worker(int $aiJobId): void
    {
        (new RunAiBoxJob($aiJobId))->handle(app(AiBoxClient::class));
    }

    /** Job qua #5 (hàng đợi database → dispatch chỉ ghi pending, chưa chạy). */
    private function queuedJob(): AiJob
    {
        $d = $this->device();
        $this->payUnlock($d, 'duyen');
        $draw = $this->drawFor($d);
        $res = $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'zq-'.Str::random(12),
        ])->assertStatus(202);

        return AiJob::query()->where('job_uuid', $res->json('data.job_uuid'))->firstOrFail();
    }

    /**
     * Mô phỏng đúng dấu vết để lại sau SIGKILL giữa claim: status=running,
     * attempts đã đếm, updated_at = thời điểm claim (không có finished_at).
     */
    private function zombify(AiJob $job, int $ageSeconds): void
    {
        $this->travelTo(now()->subSeconds($ageSeconds));
        $job->forceFill(['status' => AiJob::ST_RUNNING, 'attempts' => 1])->save();
        $this->travelBack();
    }

    public function test_xac_zombie_running_qua_nguong_duoc_doi_lai_chay_xong_done(): void
    {
        $job = $this->queuedJob();
        // worker 1 claim rồi chết đứng — xác để lại 200s trước (>180s ngưỡng)
        $this->zombify($job, 200);

        $this->fakeAi($this->cleanMd);
        $this->worker($job->id); // redelivery của worker mới

        $job->refresh();
        $this->assertSame(AiJob::ST_DONE, $job->status, 'zombie quá ngưỡng phải được đòi lại và chạy tiếp');
        $this->assertSame($this->cleanMd, $job->result);
        $this->assertNotNull($job->finished_at);
        Http::assertSentCount(1); // đúng 1 call provider cho lượt reclaim
    }

    public function test_running_con_sot_khong_bao_gio_bi_cuop_giu_hang_doi_de_redeliver(): void
    {
        $job = $this->queuedJob();
        // worker khác VẪN ĐANG sống chạy (mới claim 10s trước) — cướp = 2 worker
        // cùng gọi provider trên 1 job. Phải ném để queue redeliver (row jobs còn).
        $this->zombify($job, 10);

        $this->fakeAi($this->cleanMd);
        try {
            $this->worker($job->id);
            $this->fail('claim phải ném exception để queue giữ hàng đợi redeliver');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('zombie', $e->getMessage());
        }

        $job->refresh();
        $this->assertSame(AiJob::ST_RUNNING, $job->status, 'job của worker còn sống không bị đổi chủ');
        $this->assertNull($job->result);
        Http::assertSentCount(0); // không cướp => không call provider
    }

    public function test_job_da_done_redelivery_return_im_lang_khong_lam_lai(): void
    {
        // AC-2 giữ nguyên: cache-hit/done trước khi worker kịp lấy → skip im lặng,
        // KHÔNG ném (ném sẽ làm hàng đợi retry vô ích).
        $job = $this->queuedJob();
        $this->fakeAi($this->cleanMd);
        $this->worker($job->id);
        $job->refresh();
        $this->assertSame(AiJob::ST_DONE, $job->status);

        $this->worker($job->id); // redelivery thừa — phải về bình thường, không exception

        $job->refresh();
        $this->assertSame(AiJob::ST_DONE, $job->status);
        $this->assertSame($this->cleanMd, $job->result);
        Http::assertSentCount(1); // lượt reclaim thừa KHÔNG gọi provider lần 2
    }

    public function test_zombie_can_du_3_luot_claim_khong_doi_nua_redeliver_thanhd_terminal(): void
    {
        // Reclaim cũng tuân thủ C-04: zombie đã cạn 3 lượt claim → không đòi nữa,
        // ném để redeliver; khi tries của hàng đợi cạn, failed() tất → terminal —
        // khách KHÔNG thấy 'running' vĩnh viễn.
        $job = $this->queuedJob();
        $this->zombify($job, 200);
        $job->forceFill(['attempts' => 3])->save();

        try {
            $this->worker($job->id);
            $this->fail('claim phải ném khi cạn lượt — không chạy lại provider');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('zombie', $e->getMessage());
        }
        Http::assertSentCount(0);

        // đường của worker thật: queue gọi failed() khi cạn tries
        (new RunAiBoxJob($job->id))->failed(new \RuntimeException('boom'));

        $job->refresh();
        $this->assertSame(AiJob::ST_FAILED, $job->status, 'cạn lượt phải về terminal, không zombie vĩnh viễn');
        $this->assertSame('AI_UPSTREAM', $job->error_code);
    }

    public function test_failed_goi_tu_duoi_thanhd_failed_AI_UPSTREAM_cho_xac_con_running(): void
    {
        // #6 contract: job chết terminal qua failed() → khách thấy failed ngay,
        // không phải chờ reclaim. Đây là đường queue gọi khi tries cạn.
        $job = $this->queuedJob();
        $this->zombify($job, 200);

        (new RunAiBoxJob($job->id))->failed(new \RuntimeException('worker killed'));

        $job->refresh();
        $this->assertSame(AiJob::ST_FAILED, $job->status);
        $this->assertSame('AI_UPSTREAM', $job->error_code);
        $this->assertNull($job->result, 'xác chết không được đẻ ra bài lậu');
    }
}
