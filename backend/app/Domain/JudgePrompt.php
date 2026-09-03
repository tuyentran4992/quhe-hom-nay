<?php

namespace App\Domain;

/**
 * QUOTA-N/Q3 (card t_1bb07a82) — prompt + parse cho bước PHÁN QUYẾT paraphrase
 * ("na nan" = cùng MỘT VIỆC) giữa câu hỏi mới và bài luận done gần nhất.
 * Pure PHP như RouterPrompt (01 §2): không facade/HTTP.
 *
 *BOSS GO 03/09: day la TIET KIEM — 1 goi phan quyet re (router-model, temp 0,
 * max_tokens 8) thay 1 luot luan sau dat. Phán quyết theo NGHĨA, không trùng
 * từ khóa — chuẩn nghiệp: "hai láng giềng nhau" vs "thằng bao kia" = KHAC.
 *
 * D4 hướng nghi: mơ hồ → UNCLEAR (ghép ở InterpretationJudge xử UNCLEAR như
 * KHAC = cho hỏi thật, tính lượt — fail-open, lệnh boss). Parse cứng như
 * RouterPrompt::parse: nguyên token whitelist, mọi output khác → UNCLEAR.
 */
final class JudgePrompt
{
    /** 3 nhãn whitelist — đúng literal card (DU_GIONG|KHAC|UNCLEAR). */
    public const VERDICTS = ['DU_GIONG', 'KHAC', 'UNCLEAR'];

    public const PROMPT = <<<'TXT'
Bạn là bộ phán quyết câu hỏi cho web Chiêm nghiệm phương Đông.
Hỏi lần trước: "{old}"
Hỏi lại (mới): "{new}"
Phán quyết xem hai câu có là CÙNG MỘT VIỆC khách muốn biết (chỉ khác cách diễn đạt) hay không. So theo NGHĨA, không theo trùng từ khóa. Ví dụ chuẩn: "hai láng giềng nhau" và "thằng bao kia" là KHÁC. Câu mới mơ hồ, không chắc cùng việc → UNCLEAR.
Chỉ in ra ĐÚNG một từ trong ba khả năng: DU_GIONG | KHAC | UNCLEAR. Không giải thích, không dấu câu.
TXT;

    /** Embed hai câu đã normalize vào prompt (marker 'Hỏi lại' phân biệt với router prompt). */
    public static function forPair(string $oldQuestion, string $newQuestion): string
    {
        return str_replace(['{old}', '{new}'], [$oldQuestion, $newQuestion], self::PROMPT);
    }

    /**
     * Parse output judge về 1 trong 3 token VERDICTS; mọi thứ khác (ranh,
     * free-text, thừa dấu câu) → UNCLEAR = fail-open (D4). So sánh
     * uppercase-insensitive, nguyên token — cấm substring (án lệ RouterPrompt).
     */
    public static function parse(string $raw): string
    {
        $norm = strtoupper(trim($raw));
        foreach (self::VERDICTS as $v) {
            if ($norm === $v) {
                return $v;
            }
        }

        return 'UNCLEAR';
    }
}
