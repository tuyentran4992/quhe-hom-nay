<?php

namespace Tests\Feature\Api;

use App\Jobs\RunAiBoxJob;
use App\Models\AiJob;
use App\Services\AiBoxClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * BUG-QHN-100 — cứu hộ xác zombie mà reclaim trong RunAiBoxJob KHÔNG với tới:
 * hàng đợi jobs đã bị xoá (dấu vết của chính bug cũ: claim fail + return im lặng)
 * ⇒ không còn gì redeliver ⇒ command `ai:sweep-zombies` là đường về duy nhất.
 * Quét running quá RunAiBoxJob::zombieAfterSeconds(): còn lượt + MỒ CÔI (không còn
 * row jobs) ⇒ về queued + dispatch lại; còn row jobs ⇒ ĐỂ YÊN (worker tự
 * redeliver, cướp = 2 job chạy song song); cạn lượt ⇒ failed(AI_UPSTREAM)
 * terminal — #6 không bao giờ kẹt 'running' vĩnh viễn.
 */
class SweepZombiesTest extends Be2TestCase
{
    /** Job qua #5; fakeQueue=true ⇒ row jobs KHÔNG được ghi (mồ côi như hiện trường bug). */
    private function queuedJob(bool $fakeQueue = false): AiJob
    {
        if ($fakeQueue) {
            Queue::fake(); // dispatch của #5 bị chặn → xác mồ côi đúng hiện trường bug
        }
        $d = $this->device();
        $this->payUnlock($d, 'duyen');
        $draw = $this->drawFor($d);
        $res = $this->cookieFor($d)->postJson('/api/ai/interpretations', [
            'draw_id' => $draw->id, 'topic' => 'duyen', 'idempotency_key' => 'sq-'.Str::random(12),
        ])->assertStatus(202);
        if ($fakeQueue) {
            Queue::fake(); // reset recorder — dispatch tạo job không tính vào assertion sweep
        }

        return AiJob::query()->where('job_uuid', $res->json('data.job_uuid'))->firstOrFail();
    }

    private function zombify(AiJob $job, int $ageSeconds, int $attempts = 1): void
    {
        $this->travelTo(now()->subSeconds($ageSeconds));
        $job->forceFill(['status' => AiJob::ST_RUNNING, 'attempts' => $attempts])->save();
        $this->travelBack();
    }

    public function test_xac_mo_coi_con_luot_duoc_quet_ve_queued_va_dispatch_lai(): void
    {
        $job = $this->queuedJob(fakeQueue: true);
        $this->zombify($job, 400); // 400s > 180s ngưỡng, row jobs đã bay từ đời nào

        $this->artisan('ai:sweep-zombies')->assertSuccessful();

        $job->refresh();
        $this->assertSame(AiJob::ST_QUEUED, $job->status, 'zombie mồ côi phải về queued');
        $this->assertSame(1, $job->attempts); // sweep KHÔNG đếm thêm lượt — lượt do claim
        Queue::assertPushed(RunAiBoxJob::class, fn ($p) => $p->aiJobId === $job->id);
    }

    public function test_running_con_sot_bi_bo_qua_khong_cuop_cua_worker_song(): void
    {
        $hot = $this->queuedJob(fakeQueue: true);
        $this->zombify($hot, 10); // mới claim 10s — chủ còn sống

        $this->artisan('ai:sweep-zombies')->assertSuccessful();

        $this->assertSame(AiJob::ST_RUNNING, $hot->refresh()->status, 'đừng cướp job còn sống');
        Queue::assertNotPushed(RunAiBoxJob::class);
    }

    public function test_xac_con_hang_doi_de_yen_khong_dispatch_chong(): void
    {
        // Quá ngưỡng NHƯNG row jobs còn (pending) → worker sẽ tự redeliver,
        // handle() gặp reclaimZombie lo tiếp. Dispatch chồng = 2 worker song song.
        $job = $this->queuedJob(); // dispatch THẬT → row jobs tồn tại
        $this->zombify($job, 400);

        $this->artisan('ai:sweep-zombies')->assertSuccessful();

        $this->assertSame(AiJob::ST_RUNNING, $job->refresh()->status, 'còn hàng đợi thì KHÔNG hồi sinh tay');
    }

    public function test_zombie_can_3_luot_thanhd_failed_khong_hoi_sinh_vong_lap(): void
    {
        $job = $this->queuedJob(fakeQueue: true);
        $this->zombify($job, 400, attempts: 3);

        $this->artisan('ai:sweep-zombies')->assertSuccessful();

        $job->refresh();
        $this->assertSame(AiJob::ST_FAILED, $job->status);
        $this->assertSame('AI_UPSTREAM', $job->error_code);
        $this->assertNull($job->result);
        $this->assertNotNull($job->finished_at);
        Queue::assertNotPushed(RunAiBoxJob::class);
    }

    public function test_sweep_khong_durp_job_done_va_khong_go_providers(): void
    {
        $done = $this->queuedJob(fakeQueue: true);
        $this->fakeAi($this->cleanMd);
        (new RunAiBoxJob($done->id))->handle(app(AiBoxClient::class));
        $this->assertSame(AiJob::ST_DONE, $done->refresh()->status);
        Http::assertSentCount(1); // 1 lượt của test — sweep không được gọi thêm

        $this->artisan('ai:sweep-zombies')->assertSuccessful();

        $this->assertSame(AiJob::ST_DONE, $done->refresh()->status);
        $this->assertSame($this->cleanMd, $done->result);
        Http::assertSentCount(1); // sweep thuần DB — 0 call provider
    }
}
