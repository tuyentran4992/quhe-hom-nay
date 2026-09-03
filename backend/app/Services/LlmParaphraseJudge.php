<?php

namespace App\Services;

use App\Contracts\ParaphraseJudge;
use App\Domain\JudgePrompt;

/**
 * QUOTA-N/Q3 (card t_1bb07a82) — implement thật của seam Q2: 1 goi phan quyet
 * paraphrase qua AiBoxClient::judgeParaphrase (transport router-model: temp 0,
 * max_tokens ~8, timeout RIÊNG, fail-open NULL → UNCLEAR).
 *
 * 1 trách nhiệm: dịch câu trả lời của provider về bool "cùng nghĩa" cho cổng
 * quota — không đếm lượt (QuotaService), không tạo job (InterpretationService).
 * Khong throw: moi co che hong ben trong client (NULL) → UNCLEAR → false = KHAC
 * = cho hoi that, tinh luot (D4, lenh boss 03/09: tra sai bai ton long tin tin
 * hon mot luot thua).
 */
class LlmParaphraseJudge implements ParaphraseJudge
{
    public function __construct(private readonly AiBoxClient $client) {}

    /**
     * Verdict thô theo contract JudgePrompt (DU_GIONG|KHAC|UNCLEAR).
     * Không có nguồn so sánh (previous rỗng/null) → KHAC tức thì: 0 call,
     * không có gì để phán.
     */
    public function verdict(?string $previousQuestion, string $newQuestion): string
    {
        $old = trim((string) $previousQuestion);
        $new = trim($newQuestion);
        if ($old === '' || $new === '') {
            return self::KHAC;
        }

        return JudgePrompt::parse($this->client->judgeParaphrase($old, $new) ?? '');
    }

    /**
     * Seam Q2 isSameMeaning: CHỈ DU_GIONG là true. UNCLEAR/KHAC/lỗi → false
     * (hỏi thật) — đúng D4 "mơ hồ nghiêng về KHAC".
     */
    public function isSameMeaning(?string $previousQuestion, string $newQuestion): bool
    {
        return $this->verdict($previousQuestion, $newQuestion) === self::DU_GIONG;
    }
}
