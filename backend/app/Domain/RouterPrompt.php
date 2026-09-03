<?php

namespace App\Domain;

/**
 * LUAN-V3 (card t_a97403d8, SPEC-LUAN-V3 amended §5.2) — prompt + parse cho bước
 * ROUTER danh mục từ câu hỏi. Pure PHP: không import facade/HTTP (01 §2).
 *
 * ROUTER-FMT (card t_18927e08): whitelist nâng từ 5 nhãn-tab lên 11 DOMAIN
 * nghiệp vụ — tinh_duyen/tai_loc/cong_viec/di_lich/suc_khoe/hoc_hanh/gia_dinh/
 * phap_ly/tong_quan/KHAC + UNCLEAR. DOMAIN_TO_TAB là cầu nối thuần về 3 tab cũ:
 * PromptBuilder KHÔNG đổi signature, T-C (KHONG_THUOC_NAO) = nhánh luận chung
 * cho 6 domain ngoài tab. Giá trị router lưu thô vào ai_jobs.router_category
 * (kể cả UNCLEAR); tab chọn thuần (không question) ghi thẳng domain tương ứng.
 *
 * Văn phong bước LUẬN không đổi (verdict 01/09 16:0x) — file này chỉ phục vụ
 * call phân loại nhỏ (temp 0, max_tokens 8) chạy trước bước luận trong worker.
 * Parse: trim + uppercase-insensitive match whitelist ĐÚNG 11 token (nguyên
 * token, không substring); mọi output khác → UNCLEAR. CẤM free-text.
 */
final class RouterPrompt
{
    /** 11 domain whitelist — thứ tự display trong prompt (ROUTER-FMT §2). */
    public const DOMAINS = [
        'tinh_duyen', 'tai_loc', 'cong_viec', 'di_lich', 'suc_khoe',
        'hoc_hanh', 'gia_dinh', 'phap_ly', 'tong_quan', 'KHAC', 'UNCLEAR',
    ];

    /**
     * DOMAIN → 'topic' tab cũ mà PromptBuilder hiểu (signature V2 không đổi).
     * UNCLEAR = null → worker về luồng cũ/T-D như cũ (KHÔNG đổi UX).
     */
    public const DOMAIN_TO_TAB = [
        'tinh_duyen' => 'duyen',
        'tai_loc' => 'tai_loc',
        'cong_viec' => 'xuat_hanh',
        'di_lich' => 'xuat_hanh',
        'suc_khoe' => 'KHONG_THUOC_NAO',
        'hoc_hanh' => 'KHONG_THUOC_NAO',
        'gia_dinh' => 'KHONG_THUOC_NAO',
        'phap_ly' => 'KHONG_THUOC_NAO',
        'tong_quan' => 'KHONG_THUOC_NAO',
        'KHAC' => 'KHONG_THUOC_NAO',
        'UNCLEAR' => null,
    ];

    public const PROMPT = <<<'TXT'
Bạn là bộ phân loại câu hỏi cho web Chiêm nghiệm phương Đông.
Mười danh mục và định nghĩa:
- tinh_duyen: tình cảm đôi lứa, hôn nhân, người ấy, hợp tan, tình thân trong nhà.
- tai_loc: tiền bạc, của, nợ, đầu tư, mua bán, tài chính cá nhân.
- cong_viec: công việc đang làm, đổi việc, khởi sự, thăng tiến, đồng nghiệp.
- di_lich: đi lại, chuyến đi, dời chỗ, xuất hành xa gần trong tuần.
- suc_khoe: bệnh tật, thể trạng, nghỉ ngơi, chữa chạy của bản thân khách.
- hoc_hanh: thi cử, học hành, đậu rớt, trường lớp, bằng cấp.
- gia_dinh: cha mẹ con cái anh chị em trong nhà, việc nhà, họ hàng.
- phap_ly: giấy tờ, kiện tụng, hợp đồng, thủ tục với cơ quan nhà nước.
- tong_quan: xem tổng quát về bản thân/tương lai mà không chốt việc cụ thể nào.
- KHAC: việc có thật, hỏi rõ ràng, nhưng không rơi vào 9 danh mục trên.
UNCLEAR: câu không chứa một việc cụ thể nào để hỏi.
Quy tắc: cân nhắc danh mục theo ĐIỀU VIỆC KHÁCH MUỐN BIẾT, không theo từ khóa bề mặt ('bao giờ cưới' hỏi việc cưới → tinh_duyen; 'cưới tốn bao nhiêu tiền' hỏi túi tiền → tai_loc). Nếu do giữa hai danh mục, chọn danh mục gần việc khách hỏi nhất; nếu do giữa danh mục và KHAC, chọn KHAC.
Chỉ in ra ĐÚNG một từ trong mười một khả năng: tinh_duyen | tai_loc | cong_viec | di_lich | suc_khoe | hoc_hanh | gia_dinh | phap_ly | tong_quan | KHAC | UNCLEAR. Không giải thích, không dấu câu.
Câu hỏi: "{question}"
TXT;

    /** Embed câu hỏi đã normalize vào prompt (§5.2 — question KHÔNG bao giờ vào log). */
    public static function forQuestion(string $question): string
    {
        return str_replace('{question}', $question, self::PROMPT);
    }

    /**
     * Parse output router về 1 trong 11 token DOMAINS; mọi thứ khác → UNCLEAR.
     * So sánh uppercase-insensitive, nguyên token (không substring). Cấm free-text.
     */
    public static function parse(string $raw): string
    {
        $norm = strtoupper(trim($raw));
        foreach (self::DOMAINS as $v) {
            if ($norm === strtoupper($v)) {
                return $v;
            }
        }

        return 'UNCLEAR';
    }

    /** Domain → tab cho PromptBuilder; domain lạ/null → null (T-D như cũ). */
    public static function tabFor(?string $domain): ?string
    {
        return $domain === null ? null : (self::DOMAIN_TO_TAB[$domain] ?? null);
    }

    private function __construct() {}
}
