<?php

namespace App\Domain;

/**
 * RL-BE (card t_0e5c0eb9, D1/D2) — phần THUẦN của danh sách «Đã hỏi quẻ này»:
 * bảng nhãn 8 nhóm (nguồn LABELS.md t_RL-UX — phap_ly/tong_quan/KHAC/UNCLEAR
 * gộp «Điều cần bàn») + excerpt UTF-8-safe ≤120 code-point.
 *
 * 1 trách nhiệm: KHÔNG query, KHÔNG HTTP — nhận dữ liệu thô, trả chuỗi hiển thị.
 * Ghép DB ở DrawController::luans (controller mỏng) — chống god class (SOUL).
 *
 * Excerpt theo SAMPLE.md + trần 120 UX chốt: bỏ boilerplate đầu result
 * ("Đây là luận giải…" + '---', thường lấy từ marker '### [Hoàn cảnh]'),
 * collapse whitespace về 1 dòng, cắt tại khoảng trắng gần nhất ≤120 (tính cả
 * «…» khi bị cắt), KHÔNG chặt giữa từ, mb_* 100% UTF-8-safe.
 */
final class LuanList
{
    /** code-point trần của excerpt TÍNH CẢ «…» khi bị cắt (UX chốt 120). */
    public const EXCERPT_MAX = 120;

    /** router_category → 8 nhãn hiển thị (LABELS.md mục 2, key đúng token DOMAINS). */
    private const ROUTER_LABELS = [
        'tinh_duyen' => 'Tình duyên',
        'tai_loc' => 'Tài lộc',
        'cong_viec' => 'Công việc',
        'di_lich' => 'Đi lại',
        'suc_khoe' => 'Sức khỏe',
        'hoc_hanh' => 'Học hành',
        'gia_dinh' => 'Gia đình',
        // 4 domain tế nhị gộp về nhãn chung (lý do: LABELS.md §2)
        'phap_ly' => 'Điều cần bàn',
        'tong_quan' => 'Điều cần bàn',
        'KHAC' => 'Điều cần bàn',
        'UNCLEAR' => 'Điều cần bàn',
    ];

    /** ai_jobs.topic (enum 3 giá trị C-02) — fallback khi router NULL/lạ. */
    private const TOPIC_LABELS = [
        'duyen' => 'Tình duyên',
        'tai_loc' => 'Tài lộc',
        'xuat_hanh' => 'Xuất hành', // CEO chốt #529: KHÔNG «Công việc · đi lại»
    ];

    public const LABEL_DEFAULT = 'Điều cần bàn';

    /** D1: nhãn LUÔN có chuỗi — router thắng topic, topic thắng default. */
    public static function label(?string $routerCategory, ?string $topic): string
    {
        return self::ROUTER_LABELS[$routerCategory]
            ?? self::TOPIC_LABELS[$topic]
            ?? self::LABEL_DEFAULT;
    }

    /**
     * Preview 1 dòng ≤120 code-point cho 1 bài luận. null/rỗng → ''.
     */
    public static function excerpt(?string $result): string
    {
        $text = self::stripBoilerplate($result);
        if ($text === '') {
            return '';
        }
        // collapse mọi chuỗi whitespace (kể cả \n của markdown) về 1 dấu cách
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= self::EXCERPT_MAX) {
            return $text;
        }

        // cửa sổ 119 code-point để chỗ cho «…»; lùi về khoảng trắng gần nhất
        $cut = mb_substr($text, 0, self::EXCERPT_MAX - 1);
        $last = mb_strrpos($cut, ' ');
        $cut = $last === false ? $cut : mb_substr($cut, 0, $last);

        return rtrim($cut).'…';
    }

    /**
     * Bỏ boilerplate đầu bài: prompt V2/V3 một số model trả thêm dòng mở đầu
     * "Đây là luận giải…" + phân cách '---'. Chiến lược an toàn: nếu tìm thấy
     * marker '#[' (bất kỳ heading '[Hoàn cảnh]'/'[Vì sao'… có/không '###'),
     * cắt từ marker đó; nếu model trả cả dòng dẫn trước '#', bỏ phần trước '#'.
     * Không có gì nhận dạng được → trả nguyên văn (không bịa cắt oan).
     */
    private static function stripBoilerplate(?string $result): string
    {
        $text = trim((string) $result);
        if ($text === '') {
            return '';
        }

        if (preg_match('/^#{1,6}\s*\[/u', $text)) {
            return $text; // đã vào thẳng nội dung
        }

        // có heading giữa bài → bỏ mọi thứ trước heading đầu tiên.
        // PREG_OFFSET_CAPTURE = BYTE offset → cắt bằng substr (ranh giới UTF-8 hợp lệ tại '#'), KHÔNG mb_substr.
        if (preg_match('/^#{1,6}\s*\[/mu', $text, $m, PREG_OFFSET_CAPTURE)) {
            return ltrim(substr($text, $m[0][1]));
        }

        // không heading: cắt dòng dẫn + '---' nếu có
        if (preg_match('/^.*?\n\s*---\s*\n/us', $text, $m)) {
            return ltrim(mb_substr($text, mb_strlen($m[0])));
        }

        return $text;
    }
}
