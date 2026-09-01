<?php

namespace App\Jobs;

use App\Domain\BianRule;
use App\Domain\Luan;
use App\Domain\PromptBuilder;
use App\Domain\Rules;
use App\Domain\Wordguard;
use App\Models\AiJob;
use App\Services\AiBoxClient;
use App\Services\AiBoxException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * BE-2 — 01 §2/§3: CHỖ DUY NHẤT được đụng provider (queue DATABASE, không gọi
 * đồng bộ trong request HTTP). C-04: timeout 120s, tối đa 3 lần thử — retry NHỜ
 * queue, không sleep trong handle.
 *
 * Bất biến 1 chiều: hàng đợi chỉ lấy job queued (claim atomic UPDATE...WHERE —
 * hai worker không cùng làm 1 job). done/failed là trạng thái cuối — job đã chết
 * mà worker chết theo → lần claim sau thấy running quá timeout mới được đòi lại
 * (đơn giản hoá MVP: chỉ worker này chạy, failed luôn do code tự set).
 */
class RunAiBoxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** C-04: 3 lần thử; mỗi lần không quá 120s cộng dư giết worker. */
    public int $tries = Rules::AI_MAX_ATTEMPTS;

    public int $timeout = Rules::AI_TIMEOUT_SECONDS + 30;

    public function __construct(public int $aiJobId)
    {
        $this->onQueue('ai');
    }

    public function handle(AiBoxClient $client, ?Luan $luan = null): void
    {
        // claim atomic: queued → running; không giành được = job khác đang làm/bị skip
        $claimed = AiJob::query()
            ->where('id', $this->aiJobId)
            ->where('status', AiJob::ST_QUEUED)
            ->update(['status' => AiJob::ST_RUNNING, 'attempts' => DB::raw('attempts + 1')]);
        if ($claimed === 0) {
            return; // cache-hit job đã done trước khi worker kịp lấy — bỏ qua (AC-2)
        }

        $job = AiJob::query()->findOrFail($this->aiJobId);

        try {
            $draw = $job->draw()->with('hexagram')->firstOrFail();
            // LUAN-V2 §6 (card t_c86f3954): khối chỉ dẫn chọn lời từ BianRule —
            // case 4/5 dẫn hào TĨNH luật chọn (không phải changing_lines), case 3/6
            // MỞ nội dung quẻ biến (D2), question đã normalize đưa vào dòng hoàn cảnh.
            // Các case khác quẻ biến VẪN không bao giờ vào prompt (F10 QA giữ nguyên).
            $luan ??= new Luan;
            $changing = $draw->changing_lines ?? [];
            $hexId = (int) $draw->hexagram_id;
            $rule = BianRule::quiTrinh($changing, $hexId);
            // LUAN-V2 §6.1 case 4/5: dẫn lời hào TĨNH; các case còn lại dẫn hào động
            // như cũ (§4bis) — case 0/3/6 không có/không cần block hào.
            $positions = in_array($rule['n_dong'], [4, 5], true)
                ? array_values(array_diff(range(1, 6), $changing))
                : $changing;
            $bien = ($rule['can_loi_bien'] && $draw->bien_hexagram_id)
                ? DB::table('hexagrams')->where('id', $draw->bien_hexagram_id)->first()
                : null;
            $dungChan = $rule['n_dong'] === 6 ? $luan->dungHaoFor($hexId) : null;
            $question = ($job->question !== null && trim((string) $job->question) !== '') ? trim((string) $job->question) : null;
            $messages = [
                ['role' => 'system', 'content' => Wordguard::SYSTEM_PROMPT],
                ['role' => 'user', 'content' => PromptBuilder::userPrompt(
                    $draw->hexagram->toArray(), $job->topic, $changing,
                    $luan->haoTextsForPositions($hexId, $positions), // container inject; fallback cho test cũ gọi 1 tham số
                    $question, $rule,
                    $bien !== null ? (array) $bien : null,
                    $dungChan
                )],
            ];

            $text = $client->complete($messages);

            // 05 E4: output vi phạm wording → failed AI_FILTERED, KHÔNG lưu bài bẩn.
            $hits = Wordguard::violations($text);
            if ($hits !== []) {
                $job->transitTo(AiJob::ST_FAILED, [
                    'error_code' => 'AI_FILTERED',
                    'finished_at' => now(),
                ]);
                logger()->warning('aibox.filtered', ['job' => $job->job_uuid, 'hits' => $hits]);

                return;
            }

            $job->transitTo(AiJob::ST_DONE, ['result' => $text, 'finished_at' => now()]);
        } catch (AiBoxException $e) {
            // còn lượt thử thì throw cho queue retry; cạn 3 lượt (C-04) → failed vĩnh viễn
            if ($this->attempts() < $this->tries && $job->attempts < Rules::AI_MAX_ATTEMPTS) {
                $job->forceFill(['status' => AiJob::ST_QUEUED])->save();
                throw $e;
            }
            $job->transitTo(AiJob::ST_FAILED, ['error_code' => $e->errorCode, 'finished_at' => now()]);
        } catch (\Throwable $e) {
            if ($this->attempts() < $this->tries && $job->attempts < Rules::AI_MAX_ATTEMPTS) {
                $job->forceFill(['status' => AiJob::ST_QUEUED])->save();
                throw $e;
            }
            $job->transitTo(AiJob::ST_FAILED, ['error_code' => 'AI_UPSTREAM', 'finished_at' => now()]);
            logger()->error('aibox.crash', ['job' => $job->job_uuid, 'err' => $e->getMessage()]);
        }
    }

    /** Worker chết giữa chừng (quá 3 lượt) → #6 phải thấy failed + AI_BUSY qua error_code. */
    public function failed(\Throwable $e): void
    {
        $job = AiJob::query()->find($this->aiJobId);
        if ($job && $job->status === AiJob::ST_RUNNING) {
            $code = $e instanceof AiBoxException ? $e->errorCode : 'AI_UPSTREAM';
            $job->transitTo(AiJob::ST_FAILED, ['error_code' => $code, 'finished_at' => now()]);
        }
    }
}
