<?php

namespace App\Jobs;

use App\Domain\BianRule;
use App\Domain\Luan;
use App\Domain\PromptBuilder;
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
 * đồng bộ trong request HTTP). C-04: timeout/cooldown/attempts đọc từ
 * config/project.php (CFG-BE t_ce2a6834 — đổi số nghiệp vụ = sửa project.php,
 * không sửa code) — retry NHỜ queue, không sleep trong handle.
 *
 * Bất biến 1 chiều: hàng đợi chỉ lấy job queued (claim atomic UPDATE...WHERE —
 * hai worker không cùng làm 1 job). done/failed là trạng thái cuối — job đã chết
 * mà worker chết theo → lần claim sau thấy running quá AI_ZOMBIE_AFTER_SECONDS
 * được đòi lại (BUG-QHN-100: reclaimZombie(), lời hứa C-04 đã thành code thật);
 * running còn sốt thì NHỜ queue redeliver, không return im lặng — return im lặng
 * làm Laravel xoá row jobs, zombie kẹt 'running' vĩnh viễn.
 */
class RunAiBoxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** C-04 (project.php ai.*): 3 lần thử; mỗi lần không quá timeout+30s dư giết worker.
     *  CFG-BE: đọc config tại lúc construct — đổi số trên server nhớ `queue:restart`
     *  kèm `config:clear` (worker sống lâu cache config trong memory). */
    public int $tries;

    public int $timeout;

    public function __construct(public int $aiJobId)
    {
        $this->tries = (int) config('project.ai.max_attempts');
        $this->timeout = (int) config('project.ai.timeout_seconds') + 30;
        $this->onQueue('ai');
    }

    /**
     * BUG-QHN-100: ngưỡng đòi xác 'running' — KHÔNG phải số nghiệp vụ tự do mà
     * GIÁ TRỊ SUY DIỄN từ project.php ai.timeout_seconds: hard timeout thật của
     * worker (timeout+30 do SIGKILL) + dư 30s đồng hồ → claim sau chỉ cướp khi
     * CHỦ CŨ CHẮC CHẮN chết, không bao giờ 2 worker cùng gọi provider trên 1 job.
     * 1 nguồn duy nhất cho RunAiBoxJob + SweepZombieJobs (đừng tham số hóa —
     * đổi nó độc lập với timeout = mở cửa 2 worker giành 1 job).
     */
    public static function zombieAfterSeconds(): int
    {
        return (int) config('project.ai.timeout_seconds') + 30 + 30;
    }

    /**
     * FIX-LUAN-SAU seam đo thời gian (test budget acceptance #3): override/travel
     * qua đây thay vì microtime thật — production mặc định không đổi hành vi.
     */
    protected function now(): float
    {
        return microtime(true);
    }

    /** Ngân sách regenerate — override aibox.* (test/trường hợp đặc biệt), mặc định
     *  project.php ai.filter_regenerate_budget_s (độc quyền 1 nơi, CFG-BE). */
    protected function regenerateBudgetSeconds(): float
    {
        return (float) (config('aibox.filter_regen_budget_s')
            ?? config('project.ai.filter_regenerate_budget_s'));
    }

    public function handle(AiBoxClient $client, ?Luan $luan = null): void
    {
        // claim atomic: queued → running; không giành được = job khác đang làm/bị skip
        $claimed = AiJob::query()
            ->where('id', $this->aiJobId)
            ->where('status', AiJob::ST_QUEUED)
            ->update(['status' => AiJob::ST_RUNNING, 'attempts' => DB::raw('attempts + 1')]);
        if ($claimed === 0) {
            $claimed = $this->reclaimZombie();
        }
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

            // ── LUAN-V3 (SPEC §5.3, ADR-V3-01): bước ROUTER danh mục ──────────────
            // CHỈ chạy khi có question. route='UNCLEAR' → questionForPrompt=null về
            // luồng cũ (câu hỏi vẫn lưu DB, FE vẫn hiển thị "Bạn hỏi:"); route=null
            // (lỗi mạng/timeout) → T-D fallback tự xử — KHÔNG throw, KHÔNG fail job.
            // Cross-tab KHÔNG đụng entitlement: khách mua tab nào trả tiền tab đó,
            // ai_jobs.topic giữ nguyên tab — router chỉ đổi prompt content (quyết
            // định nghiệp vụ anh Tuyền chốt §5.3). Cache D1 không đổi: job có question
            // vốn không ăn cache (InterpretationService:114-117).
            $route = null;
            $questionForPrompt = $question;
            if ($question !== null) {
                $route = $client->routeTopic($question);
                if ($route === 'UNCLEAR') {
                    $questionForPrompt = null;
                }
            }
            $routedTopic = in_array($route, ['duyen', 'tai_loc', 'xuat_hanh', 'KHONG_THUOC_NAO'], true) ? $route : null;

            $messages = [
                ['role' => 'system', 'content' => Wordguard::SYSTEM_PROMPT],
                ['role' => 'user', 'content' => PromptBuilder::userPrompt(
                    $draw->hexagram->toArray(), $job->topic, $changing,
                    $luan->haoTextsForPositions($hexId, $positions), // container inject; fallback cho test cũ gọi 1 tham số
                    $questionForPrompt, $rule,
                    $bien !== null ? (array) $bien : null,
                    $dungChan,
                    $routedTopic
                )],
            ];

            // FIX-LUAN-SAU 02/09 (OBS-FILTER t_c146e45a): model thỉnh thoảng sinh
            // chữ dính wordguard (hay nhất "cốt" trong "cốt lõi" — regex \bc[oố]t\b
            // bắt cả từ ghép nghĩa) → trước dây fail thẳng AI_FILTERED, user thấy
            // "bàn cờ im tiếng" dù chỉ lệch MỘT chữ. Nay tự regenerate tối đa
            // project.php ai.filter_regenerations lần ngay trong handle: gửi lại chính
            // xác chữ phạm + yêu cầu viết đủ bài KHÔNG dùng chữ đó. Vẫn giữ hợp
            // đồng: bài bẩn KHÔNG BAO GIỜ lưu; cạn lượt regenerate → failed
            // AI_FILTERED như cũ (E4).
            //
            // Chặn ngân sách thời gian: user poll tối đa AI_POLL_MAX_MS 130s, worker
            // timeout 150s — chỉ regenerate khi lượt đầu chạy dưới
            // AI_FILTER_REGENERATE_BUDGET_S, để lần 2 còn cửa về kịp trong khung
            // poll; lượt đầu đã chậm thì thà fail sớm cho user bấm thử lại.
            $startedAt = $this->now();
            $regen = 0;
            do {
                $text = Wordguard::stripBoldMarkers($client->complete($messages));
                $hits = Wordguard::violations($text);
                if ($hits === []) {
                    break;
                }
                if ($regen >= (int) config('project.ai.filter_regenerations')
                    || ($this->now() - $startedAt) > $this->regenerateBudgetSeconds()) {
                    break;
                }
                $regen++;
                $words = Wordguard::matchedWords($text);
                logger()->warning('aibox.filtered_regenerate', [
                    'job' => $job->job_uuid, 'hits' => $hits, 'regen' => $regen,
                ]);
                // feedback ngắn gọn appended — system prompt + bài cũ làm ngữ cảnh,
                // model chỉ việc viết lại đủ bài sạch chữ phạm.
                $messages[] = ['role' => 'assistant', 'content' => $text];
                $messages[] = ['role' => 'user', 'content' =>
                    'Bài trên chứa chữ cấm: '.implode(', ', $words).'.'
                    .' Viết lại TOÀN BỘ bài luận (giữ đúng cấu trúc, độ dài, nội dung),'
                    .' tuyệt đối KHÔNG xuất hiện các chữ: '.implode(', ', $words).'.'
                    .' (Ví dụ "cốt lõi" → dùng "giá trị nền tảng".) Chỉ xuất bài hoàn chỉnh, không lời dẫn.'];
            } while (true);

            // 05 E4: output VẪN vi phạm sau regenerate → failed AI_FILTERED, không lưu bài bẩn.
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
            // còn lượt thử thì throw cho queue retry; cạn tries (C-04, project.php)
            // → failed vĩnh viễn
            $maxAttempts = (int) config('project.ai.max_attempts');
            if ($this->attempts() < $this->tries && $job->attempts < $maxAttempts) {
                $job->forceFill(['status' => AiJob::ST_QUEUED])->save();
                throw $e;
            }
            $job->transitTo(AiJob::ST_FAILED, ['error_code' => $e->errorCode, 'finished_at' => now()]);
        } catch (\Throwable $e) {
            $maxAttempts = (int) config('project.ai.max_attempts');
            if ($this->attempts() < $this->tries && $job->attempts < $maxAttempts) {
                $job->forceFill(['status' => AiJob::ST_QUEUED])->save();
                throw $e;
            }
            $job->transitTo(AiJob::ST_FAILED, ['error_code' => 'AI_UPSTREAM', 'finished_at' => now()]);
            logger()->error('aibox.crash', ['job' => $job->job_uuid, 'err' => $e->getMessage()]);
        }
    }

    /**
     * BUG-QHN-100 (QA t_00c3fb07) — lời hứa docblock C-04 thành HIỆN THỰC:
     * worker chết giữa chứng để lại xác 'running'; hàng đợi redeliver làm claim
     * chuẩn (queued→running) fail. Trước đây hàm handle return im lặng → Laravel
     * xoá row jobs → zombie VĨNH VIỄN, #6 trả 'running' tới mãi. Phân xử theo
     * chính xác, atomic 1 UPDATE kèm điều kiện status (không đọc-rồi-ghi):
     *   - running quá zombieAfterSeconds() (hard-timeout 150s mặc định
     *     + dư 30s) còn lượt ⇒ đòi xác, trả 1 để handle chạy tiếp như lượt thường;
     *   - running còn sốt (chủ cũ còn sống — cướp = 2 worker gọi provider trên
     *     1 job) HOẶC cạn lượt ⇒ ném exception: queue GIỮ row jobs, redeliver
     *     sau retry_after; cạn tries của queue → failed() tất terminal —
     *     #6 không bao giờ kẹt 'running';
     *   - done/failed/mất hàng ⇒ trả 0: skip im lặng như cũ (AC-2 cache-hit).
     */
    private function reclaimZombie(): int
    {
        $reclaimed = AiJob::query()
            ->where('id', $this->aiJobId)
            ->where('status', AiJob::ST_RUNNING)
            ->where('attempts', '<', (int) config('project.ai.max_attempts'))
            ->where('updated_at', '<', now()->subSeconds(self::zombieAfterSeconds()))
            ->update(['attempts' => DB::raw('attempts + 1')]);
        if ($reclaimed === 1) {
            logger()->warning('aibox.zombie_reclaim', ['job' => $this->aiJobId]);

            return 1;
        }

        $status = AiJob::query()->where('id', $this->aiJobId)->value('status');
        if ($status === AiJob::ST_RUNNING) {
            // còn sốt hoặc cạn lượt — giữ đường về, chờ lần redeliver kế tiếp
            throw new \RuntimeException("ai_jobs#{$this->aiJobId}: zombie chưa tới ngưỡng hoặc cạn lượt — chờ redeliver");
        }

        return 0;
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
