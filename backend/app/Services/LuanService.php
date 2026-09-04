<?php

namespace App\Services;

use App\Domain\LuanList;
use App\Models\AiJob;
use Illuminate\Support\Collection;

/**
 * RL-BE (card t_0e5c0eb9, D1/D2) — đọc danh sách bài đã luận của MỘT quẻ cho
 * GET /api/draws/{draw_id}/luans. 1 trách nhiệm: truy vấn + lắp payload;
 * nhãn/excerpt delegate sang Domain\LuanList thuần; KHÔNG ghi, KHÔNG gọi AI.
 *
 * Filter chốt D2: status=done AND from_cache=0 (loại bóng ma DU_GIONG) — dedupe
 * theo NỘI DUNG result giữa finished_at mới nhất, làm trong PHP trên tập đã lọc
 * (0 query thêm). Không tin result_key_hash làm khóa dedupe: cột đó là hash
 * idempotency sha(draw|topic|question) (InterpretationService dòng 100), hai
 * question khác nhau cùng văn bản ra => hash khác nhau mà không bắt được bài
 * trùng kiểu seed quhe_uxrqa id3/4.
 *
 * Chọn cột đích, CẤM SELECT * (luật dev-lead Q-A mục 3 — result MEDIUMTEXT đã
 * là cột to nhất, kéo thêm prompt/error chỉ phí băng thông). draw_id đã có
 * index fk_ai_jobs_draw — 1 query/quẻ, không N+1.
 */
class LuanService
{
    /**
     * Danh sách bài done của 1 quẻ (draw đã được caller kiểm quyền device).
     * Sort SQL: finished_at NULL xuống cuối, DESC, tie-break id DESC; dedupe
     * PHP duyệt theo thứ tự đó → lần gặp ĐẦU = bản mới nhất, các bản sau cùng
     * nội dung bị bỏ.
     *
     * @return list<array<string, mixed>>
     */
    public function listForDraw(int $drawId): array
    {
        $rows = AiJob::query()
            ->select(['id', 'job_uuid', 'topic', 'question', 'router_category', 'result', 'finished_at'])
            ->where('draw_id', $drawId)
            ->where('status', AiJob::ST_DONE)
            ->where('from_cache', false)
            ->orderByRaw('(finished_at IS NULL) ASC, finished_at DESC, id DESC')
            ->get();

        return $this->dedupeByContent($rows)
            ->map(static fn (AiJob $j): array => [
                'id' => (int) $j->id,
                'job_uuid' => (string) $j->job_uuid,
                'topic' => (string) $j->topic,
                'router_category' => $j->router_category !== null ? (string) $j->router_category : null,
                'label' => LuanList::label($j->router_category, $j->topic),
                'question' => $j->question !== null ? (string) $j->question : null,
                'excerpt' => LuanList::excerpt($j->result),
                'finished_at' => $j->finished_at?->format('Y-m-d\TH:i:s\Z'),
                'result' => (string) $j->result,
            ])
            ->values()
            ->all();
    }

    /** @param Collection<int, AiJob> $rows @return Collection<int, AiJob> */
    private function dedupeByContent(Collection $rows): Collection
    {
        $seen = [];

        return $rows->filter(static function (AiJob $j) use (&$seen): bool {
            $key = md5((string) $j->result);
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        });
    }
}
