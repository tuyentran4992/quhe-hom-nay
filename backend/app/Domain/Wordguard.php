<?php

namespace App\Domain;

/**
 * BE-2 — bộ lọc wording bắt buộc (01 §1 + 05 E4): sản phẩm là "giải trí / tham khảo
 * văn hoá"; CẤM bán ritual và câu chữ hứa hẹn đổi vận.
 * Pure PHP. Dùng cho: (1) prompt system, (2) kiểm output AI trước khi lưu — vi phạm
 * → job failed error_code=AI_FILTERED (03-api §6).
 */
final class Wordguard
{
    /** Danh sách từ/cụm cấm, regex không dấu (giống 05-testplan E4). */
    public const BANNED_PATTERNS = [
        'h[oó]a gi[aả]i', 'c[uú]ng', 'gi[aả]i h[aạ]n', 'b[uù]a',
        'thay d[oổ]i v[aậ]n m[eệ]nh', 't[aâ]m linh', 'th[iỉ]nh', 'c[oố]t',
    ];

    /**
     * Prompt system nhúng luật wording — nguồn CHỖ DUY NHẤT (card BE-2 khóa).
     * %topic% do PromptBuilder thay.
     */
    public const SYSTEM_PROMPT = <<<'TXT'
Bạn là người viết nội dung "luận giải chuyên sâu" cho web Chiêm nghiệm phương Đông "Quẻ Hôm Nay".
QUY TẮC BẮT BUỘC:
- Đây là nội dung GIẢI TRÍ / THAM KHẢO VĂN HOÁ về Kinh Dịch. Giọng điệu điềm đạm, thiên về suy ngẫm.
- CẤM hoàn toàn: nghi lễ cúng kiếng, giải hạn, bùa chú, "thỉnh", cốt, tâm linh như dịch vụ.
- CẤM hứa hẹn "thay đổi vận mệnh" hay bất kỳ kết quả thực tế nào (tiền, sức khoẻ, hôn nhân).
- Không ra chỉ thị hành động mang tính mê tín (hướng đặt lễ, ngày giờ cúng...).
- Trả lời tiếng Việt, markdown nhẹ, 200–400 từ, đúng 1 chủ đề được yêu cầu.
- Kết bài nhắc nhẹ: "chỉ mang tính tham khảo giải trí về văn hoá".
TXT;

    /** @return list<string> các mẫu cấm bị khớp trong text (rỗng = sạch) */
    public static function violations(string $text): array
    {
        $hits = [];
        foreach (self::BANNED_PATTERNS as $p) {
            if (preg_match('/\b(' . $p . ')\b/iu', $text)) {
                $hits[] = $p;
            }
        }

        return $hits;
    }

    public static function isClean(string $text): bool
    {
        return self::violations($text) === [];
    }

    private function __construct()
    {
    }
}
