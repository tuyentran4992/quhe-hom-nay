<?php

namespace App\Domain;

/**
 * BE-2 — dựng prompt luận sâu từ dữ liệu quẻ (03-api §6 yêu cầu markdown 200–400 từ).
 * Pure PHP: nhận mảng dữ liệu thuần, không import facade/HTTP (01 §2).
 * System prompt NHÚNG LUẬT WORDING 01 §1 (Wordguard::SYSTEM_PROMPT) — card BE-2 khóa.
 *
 * LUAN-V2 (SPEC §6, card t_c86f3954) — NỢ §4bis CẬP NHẬT:
 *   Quẻ biến vào prompt ĐƯỢC PHÉP duy nhất khi BianRule::quiTrinh() trả
 *   can_loi_bien=true (case 3 + 6 — theo lệnh anh Tuyền P2, CEO chốt D2).
 *   Case 0/1/2/4/5 VẪN CẤM tuyệt đối nội dung biến — test chống leak
 *   (BienLuuVaPromptTest + PromptLuanV2Test) giữ nguyên độ nghiêm ngặt:
 *   leak khi rule không đòi = fail review.
 *   Kèm: điều CẤM bịa hoàn cảnh khi question trống (§6d) + bố cục BẮT BUỘC
 *   3 phần marker [Hoàn cảnh]/[Vì sao khuyên vậy]/[Việc nên làm cụ thể tuần này].
 */
final class PromptBuilder
{
    /**
     * @param array $hex 1 row bảng hexagrams (snake_case) — quẻ GỐC
     * @param int[] $changingLines vị trí 1-based (hào động)
     * @param array[] $haoTexts các hào luật Biện quẻ CHỌN (§6.1: case 4/5 là hào
     *        TĨNH — worker lọc bằng Luan::haoTextsForPositions, không phải
     *        changing_lines): {vi,hao,han,quoc_am,nghia}. RỖNG khi 0 động.
     * @param string|null $question câu hỏi khách ĐÃ normalize (trim, null nếu rỗng)
     * @param array|null $rule kết quả BianRule::quiTrinh(); null → tự tính
     * @param array|null $bien row quẻ BIẾN (hexagrams, snake_case) — CHỈ được dùng
     *        khi $rule['can_loi_bien'] (case 3/6); truyền vào mà rule cấm → bỏ qua.
     * @param array|null $dungChan lời DỤNG hào (Luan::dungHaoFor) cho case 6 Càn/Khôn.
     */
    public static function userPrompt(
        array $hex,
        string $topic,
        array $changingLines,
        array $haoTexts = [],
        ?string $question = null,
        ?array $rule = null,
        ?array $bien = null,
        ?array $dungChan = null,
    ): string {
        $topicLabel = match ($topic) {
            'duyen' => 'tình duyên',
            'tai_loc' => 'tài lộc',
            'xuat_hanh' => 'xuất hành',
            default => $topic,
        };
        $rule ??= BianRule::quiTrinh($changingLines, isset($hex['id']) ? (int) $hex['id'] : null);
        $question = ($question !== null && trim($question) !== '') ? trim($question) : null;

        // QA MERGE SHIM (t_5cd31bb9): BE-1 model cast JSON column thanh array,
        // BE-2 PromptBuilder doi string. Chap nhan ca hai — dev-lead ghe 1 kieu khi merge main.
        $free = is_array($hex['free_content'] ?? null) ? $hex['free_content'] : (json_decode((string) ($hex['free_content'] ?? '{}'), true) ?: []);
        $kw = is_array($hex['keywords'] ?? null) ? $hex['keywords'] : (json_decode((string) ($hex['keywords'] ?? '[]'), true) ?: []);
        $lines = implode(',', $changingLines ?: []);

        // §6 header cũ giữ nguyên 5 dòng + dòng hào động; THAY câu "ưu tiên luận theo
        // tượng hào động" bằng khối (b) chỉ dẫn chọn lời của BianRule.
        $head = [
            "Chủ đề luận sâu: {$topicLabel}.",
            'Quẻ gốc (Hán: '.($hex['han'] ?? '').', tên: '.($hex['ten'] ?? '').'): '.($hex['symbol'] ?? ''),
            'Đại ý: '.($hex['dai_ci'] ?? ''),
            'Từ khóa: '.implode(', ', (array) $kw),
            'Luận hôm nay: '.($hex['luan_nay'] ?? ''),
            'Góc nhìn sẵn có về '.$topicLabel.': '.($free[static::freeKey($topic)] ?? '—'),
            $lines !== '' ? "Hào động (1-based từ dưới lên): {$lines}" : 'Không có hào động.',
            'Luật Biện quẻ (số hào động: '.$rule['n_dong'].'): '.$rule['loi_luan'],
        ];

        // (a) dòng hoàn cảnh — chỉ khi có question.
        if ($question !== null) {
            $head[] = "Khách đang vướng: \"{$question}\"";
        }

        // yaoBlock: các hào ĐƯỢC RULE CHỌN — nhãn 'Hào động' giữ nguyên format cũ
        // cho test cũ; riêng case 4/5 thêm chữ (tĩnh) vào ngoặc sau vi{N} — test cũ
        // chỉ grep 'Hào động vi' nên không vỡ.
        $yaoBlock = [];
        foreach ($haoTexts as $t) {
            $yaoBlock[] = sprintf(
                'Hào động vi%d (%s%s) — Hán: %s | Quốc âm: %s | Nghĩa: %s',
                (int) ($t['vi'] ?? 0),
                (string) ($t['hao'] ?? ''),
                in_array((int) ($t['vi'] ?? 0), $changingLines, true) ? '' : ', hào tĩnh luật chọn',
                trim((string) ($t['han'] ?? '')),
                trim((string) ($t['quoc_am'] ?? '')),
                trim((string) ($t['nghia'] ?? '')),
            );
        }

        // (b) khối biến — CHỈ khi rule mở (D2: case 3 + 6). Leak khi rule cấm = fail.
        $bienBlock = [];
        if ($rule['can_loi_bien'] && $bien !== null) {
            $bienBlock[] = 'Quẻ biến (Hán: '.($bien['han'] ?? '').', tên: '.($bien['ten'] ?? '').'): '.($bien['dai_ci'] ?? '');
        }
        if ($rule['n_dong'] === 6 && $rule['can_loi_bien'] && $dungChan !== null) {
            // case 6 Càn/Khôn: lời DỤNG là nội dung biến hợp lệ (§3.3), không phải quẻ từ.
            $bienBlock[] = "Lời dụng ({$dungChan['han']} | {$dungChan['am']} | {$dungChan['nghia']})";
        }

        // (c) bố cục BẮT BUỘC 3 phần + (d) điều cấm khi không có question.
        $tail = [
            'Bố cục BẮT BUỘC 3 phần, đúng thứ tự, không thêm phần ngoài 3 phần:',
            '[Hoàn cảnh] — khung tình huống quẻ chỉ ra cho chủ đề '.$topicLabel.'.',
            '[Vì sao khuyên vậy] — dẫn lời quẻ/hào từ mà luật Biện quẻ ở trên đã chọn, giải thích lý do.',
            '[Việc nên làm cụ thể tuần này — tối đa 3 gạch đầu dòng] — hành động đời thường, không nghi lễ.',
            'Giữ 200–400 từ, tiếng Việt, văn phong tham khảo văn hoá.',
        ];
        if ($question === null) {
            $tail[] = 'CẤM bịa hoặc đoán hoàn cảnh riêng của khách. Không có câu hỏi nào được nêu — chỉ luận thế quẻ chung cho chủ đề.';
        }

        return implode("\n", array_merge($head, $yaoBlock, $bienBlock, $tail));
    }

    private static function freeKey(string $topic): string
    {
        return match ($topic) {
            'duyen' => 'tinhDuyen',
            'tai_loc' => 'taiLoc',
            'xuat_hanh' => 'congViec',
        };
    }

    private function __construct()
    {
    }
}
