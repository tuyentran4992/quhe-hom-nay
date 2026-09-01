<?php

namespace App\Domain;

/**
 * LUAN-V3 (card t_a97403d8, SPEC-LUAN-V3 amended §5.2) — prompt + parse cho bước
 * ROUTER danh mục từ câu hỏi. Pure PHP: không import facade/HTTP (01 §2).
 *
 * Văn phong bước LUẬN không đổi (verdict 01/09 16:0x) — file này chỉ phục vụ
 * call phân loại nhỏ (temp 0, max_tokens 8) chạy trước bước luận trong worker.
 * Parse: trim + uppercase-insensitive match whitelist ĐÚNG 5 giá trị (bản
 * 056beba ghi nhầm "sáu khả năng" — amended §5.2 chốt 5, bug số học).
 * Mọi output khác (văn bản thừa, rỗng) → UNCLEAR = về luồng cũ, KHÔNG phải lỗi.
 */
final class RouterPrompt
{
    /** 5 giá trị whitelist — thứ tự display trong prompt §5.2. */
    public const VALUES = ['duyen', 'tai_loc', 'xuat_hanh', 'KHONG_THUOC_NAO', 'UNCLEAR'];

    public const PROMPT = <<<'TXT'
Bạn là bộ phân loại câu hỏi cho web Chiêm nghiệm phương Đông.
Ba danh mục và định nghĩa:
- duyen: tình cảm đôi lứa, hôn nhân, người ấy, hợp tan, tình thân trong nhà.
- tai_loc: tiền bạc, của, nợ, đầu tư, mua bán, tài chính cá nhân.
- xuat_hanh: đi lại, đổi việc, công việc đang làm, khởi sự, chuyện xa gần trong tuần.
KHONG_THUOC_NAO: việc không rơi vào đúng 3 danh mục trên (sức khỏe, học hành, pháp lý, xem số tổng quát…).
UNCLEAR: câu không chứa một việc cụ thể nào để hỏi.
Quy tắc: cân nhắc danh mục theo ĐIỀU VIỆC KHÁCH MUỐN BIẾT, không theo từ khóa bề mặt ('bao giờ cưới' hỏi việc cưới → duyen; 'cưới tốn bao nhiêu tiền' hỏi túi tiền → tai_loc). Nếu do giữa hai danh mục hoặc do giữa danh mục và KHONG_THUOC_NAO, chọn KHONG_THUOC_NAO.
Chỉ in ra ĐÚNG một từ trong năm khả năng: duyen | tai_loc | xuat_hanh | KHONG_THUOC_NAO | UNCLEAR. Không giải thích, không dấu câu.
Câu hỏi: "{question}"
TXT;

    /** Embed câu hỏi đã normalize vào prompt (§5.2 — question KHÔNG bao giờ vào log). */
    public static function forQuestion(string $question): string
    {
        return str_replace('{question}', $question, self::PROMPT);
    }

    /**
     * Parse output router về 1 trong 5 giá trị whitelist; mọi thứ khác → UNCLEAR.
     * So sánh uppercase-insensitive, nguyên token (không substring).
     */
    public static function parse(string $raw): string
    {
        $norm = strtoupper(trim($raw));
        foreach (self::VALUES as $v) {
            if ($norm === strtoupper($v)) {
                return $v;
            }
        }

        return 'UNCLEAR';
    }

    private function __construct()
    {
    }
}
