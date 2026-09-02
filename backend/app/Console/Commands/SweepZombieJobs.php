<?php

namespace App\Console\Commands;

use App\Domain\Rules;
use App\Jobs\RunAiBoxJob;
use App\Models\AiJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * BUG-QHN-100 (QA t_00c3fb07) — ĐƯỜNG CỨU HỘ cho xác zombie mà reclaim trong
 * RunAiBoxJob::handle() không với tới: chính bug cũ (claim fail → return im
 * lặng) đã làm Laravel XOÁ row jobs, nên chẳng còn gì redeliver để handle lần
 * sau đòi lại. Command quét tay hàng đợi DB:
 *
 *   - running quá Rules::AI_ZOMBIE_AFTER_SECONDS + còn lượt + MỒ CÔI (không còn
 *     row jobs) ⇒ đưa về queued + dispatch lại (worker thường nhặt việc);
 *   - running còn row jobs ⇒ ĐỂ YÊN — worker tự redeliver, reclaimZombie() lo
 *     (dispatch chồng = 2 worker song song trên cùng job, cấm);
 *   - running quá ngưỡng cạn 3 lượt ⇒ failed(AI_UPSTREAM) terminal — #6 trả về
 *     dứt điểm, FE thoát vòng poll vô hạn.
 *
 * Chạy an toàn nhiều lần (mỗi bước UPDATE kèm điều kiện status/updated_at —
 * chủ sống xen giữa thì thua race, không ghi đè). Hạ tầng hiện chỉ có
 * queue:work (01 §2, không scheduler) ⇒ chạy tay khi giám sát thấy running treo;
 * gắn cron sau này nếu ops muốn.
 */
class SweepZombieJobs extends Command
{
    protected $signature = 'ai:sweep-zombies';

    protected $description = 'Reclaim ai_jobs kẹt running khi worker chết giữa job (BUG-QHN-100)';

    public function handle(): int
    {
        $cutoff = now()->subSeconds(Rules::AI_ZOMBIE_AFTER_SECONDS);
        $queueTable = config('queue.connections.database.table', 'jobs');

        // Set job id còn nằm TRONG hàng đợi pending — decode + unserialize đúng
        // như worker thật làm (payload database: data.command = chuỗi serialize).
        $queuedIds = [];
        DB::table($queueTable)->where('queue', 'ai')->orderBy('id')->select('payload')->each(function ($row) use (&$queuedIds) {
            $command = json_decode((string) $row->payload, true)['data']['command'] ?? null;
            if (! is_string($command)) {
                return;
            }
            $payload = @unserialize($command, ['allowed_classes' => [RunAiBoxJob::class]]);
            if ($payload instanceof RunAiBoxJob) {
                $queuedIds[$payload->aiJobId] = true;
            }
        });

        $stale = AiJob::query()
            ->where('status', AiJob::ST_RUNNING)
            ->where('updated_at', '<', $cutoff)
            ->orderBy('id')
            ->get();

        $revived = 0;
        $settled = 0;
        $skipped = 0;
        foreach ($stale as $job) {
            if (isset($queuedIds[$job->id])) {
                $skipped++; // còn hàng đợi → worker tự redeliver, reclaimZombie xử

                continue;
            }
            if ($job->attempts >= Rules::AI_MAX_ATTEMPTS) {
                // transitTo chặn ngược/dẫm chân: ai vừa claim giữa chừng → status
                // không còn running, RuntimeException (worker thật sẽ không kịp;
                // sweep coi như bỏ qua xác này).
                try {
                    $job->transitTo(AiJob::ST_FAILED, [
                        'error_code' => 'AI_UPSTREAM',
                        'finished_at' => now(),
                    ]);
                    $settled++;
                    logger()->warning('aibox.zombie_sweep_failed', ['job' => $job->job_uuid]);
                } catch (\RuntimeException $e) {
                    $skipped++;
                }

                continue;
            }
            // atomic: chỉ đổi running→queued nếu VẪN là xác cũ — updated_at đã
            // nhúc nhích (chủ sống heartbeat) thì thua race, để redeliver lo.
            $won = AiJob::query()
                ->where('id', $job->id)
                ->where('status', AiJob::ST_RUNNING)
                ->where('updated_at', '<', $cutoff)
                ->update(['status' => AiJob::ST_QUEUED]);
            if ($won === 1) {
                RunAiBoxJob::dispatch($job->id);
                $revived++;
                logger()->warning('aibox.zombie_sweep_revive', ['job' => $job->job_uuid]);
            }
        }

        $this->info("zombie sweep: hoi_sinh={$revived} terminal={$settled} bo_qua={$skipped}");

        return self::SUCCESS;
    }
}
