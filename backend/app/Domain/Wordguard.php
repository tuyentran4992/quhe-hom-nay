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

    /**
     * FIX-LUAN-SAU 02/09 — trích CHÍNH XÁC đoạn chữ khớp cấm (đã strip ký tự điều
     * khiển) để đưa vào feedback regenerate: model sửa đúng chữ thay vì đoán mò.
     * Mỗi pattern lấy match đầu tiên; trả danh sách unique theo thứ tự pattern.
     *
     * @return list<string> vd ['cốt'] khi text chứa "cốt lõi"
     */
    public static function matchedWords(string $text): array
    {
        $words = [];
        foreach (self::BANNED_PATTERNS as $p) {
            if (preg_match('/\b(' . $p . ')\b/iu', $text, $m)) {
                $w = mb_strtolower($m[1]);
                if (! in_array($w, $words, true)) {
                    $words[] = $w;
                }
            }
        }

        return $words;
    }

    public static function isClean(string $text): bool
    {
        return self::violations($text) === [];
    }

    /**
     * BUG-V3-4 (card t_fc8a8953) — normalize markdown bold MOT CHO duoi quyen BE
     * (hop dong 1-cho-duy-nhat BUG-V3-2, ghi ro trong FE luanRender.js: "BE
     * normalize mot cho truoc khi luu result — CAM ca hai noi cung lam").
     *
     * Chi mat literal `**`: TopicGate render whitespace-pre-wrap, khong co markdown
     * renderer → moi `**` deu la marker tho den mat khach (bang chung QA: DB_162
     * cap dong, DB_50/51 3 dong/bai). Van prose tieng Viet hop le CHUA BAO GIO
     * chua `**`, nen doi `**x**` -> `x` bang strip toan bo `**` la du mat nghiem
     * nghiem cua card:
     *  - CAM an nham ky tu thuong: moi dau `*` le (italic don cuoi bai, tich nhan
     *    "2*3"), chu, dau câu, unicode giu NGUYEN ven — str_replace khong overlapping
     *    bien `***x***` -> `*x*` (con giu italic), khong them cat bot gi khac.
     *  - `**` khong dong cap: cung la marker tho → strip het → bao dam bien
     *    acceptance QA "grep ** tren result = 0".
     *  - Idempotent: ket qua sau chay khong con `**` → chay tiep = khong doi.
     */
    public static function stripBoldMarkers(string $text): string
    {
        return str_replace('**', '', $text);
    }

    private function __construct()
    {
    }
}
