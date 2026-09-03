<?php

namespace App\Contracts;

/**
 * QUOTA-N/Q2 (card t_1b5a0c23, D2.2) — DIEM MO RONG cho card Q3 (judge
 * paraphrase). Q3 KHÔNG ở card này; interface 1 hàm là chỗ ghép duy nhất:
 * khi #5 gặp câu hỏi KHÁC văn bản cho cùng (quẻ, topic), Q3 quyết định
 * DU_GIONG (trả bài cũ, job từ tạo with from_cache=true — 0 dem quota) hay
 * KHAC (cho hỏi that, tinh luot quota).
 *
 * Fail-open theo D4 (lenh boss): ket lua khong ro / loi => tra false (KHAC),
 * cho hoi that va tinh luot — thien an toan chi phi ve phia "hoi that".
 *
 * Binding implement (Q3): AppServiceProvider::register() bind interface →
 * judge that; mac dinh CHUA co binding = hanh vi hien tai (moi hoi khac
 * question la hoi that).
 */
interface ParaphraseJudge
{
    /** 3 nhãn phán quyết (Q3, card t_1bb07a82) — đúng literal card/BOSS-GO. */
    public const DU_GIONG = 'DU_GIONG';

    public const KHAC = 'KHAC';

    public const UNCLEAR = 'UNCLEAR';

    /**
     * Hai câu hỏi có phải cùng một ý (DU_GIONG) không?
     * $previousQuestion = question của job done gần nhất cùng (hexagram, topic)
     * (nguồn findDoneSource); $newQuestion = câu vừa xin, đã trim.
     */
    public function isSameMeaning(?string $previousQuestion, string $newQuestion): bool;
}
