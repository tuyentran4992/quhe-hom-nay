<?php

namespace App\Services;

use App\Models\AiJob;

/**
 * QUOTA-N/Q2 (card t_1b5a0c23) — chủ nợ duy nhất của phép đếm "luận sau THAT".
 * 1 trách nhiệm: đếm job done THẬT theo draw_id (loại row from_cache) + suy
 * remaining theo config. InterpretationService (cổng 429) và MeController
 * (remaining_deep_reads) cùng đọc qua đây — đếm 2 chỗ = 2 công thức lệch nhau.
 *
 * Đếm done (không đếm queued/running/failed): chỉ bàiđã-sinh-ra-moi-có-gia-tri
 * chi phi; that bai = khong dem (matrix a3, AiWorkerTest: failed → FE bấm thử lại).
 */
class QuotaService
{
    /** Lượt luận sau THAT đã dùng của 1 quẻ: done && !from_cache (D3). */
    public function realDoneCount(int $drawId): int
    {
        return AiJob::query()
            ->where('draw_id', $drawId)
            ->where('status', AiJob::ST_DONE)
            ->where('from_cache', false)
            ->count();
    }

    /** Ngưỡng N hiện hành (D1 — project.php, env MAX_DEEP_READS_PER_DRAW). */
    public function maxPerDraw(): int
    {
        return max(1, (int) config('project.ai.max_deep_reads_per_draw'));
    }

    /** remaining_deep_reads cho API #1/#10 = max(0, N − lượt thật) — FE "còn x/N". */
    public function remaining(int $drawId): int
    {
        return max(0, $this->maxPerDraw() - $this->realDoneCount($drawId));
    }

    /** Còn cửa gọi provider thật cho quẻ này không (gate #5 trước INSERT). */
    public function exhausted(int $drawId): bool
    {
        return $this->realDoneCount($drawId) >= $this->maxPerDraw();
    }
}
