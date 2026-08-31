<?php

namespace App\Domain;

/**
 * BUG-F7-QA3 (t_b0cfc1b4) — clip hook hiển thị ≤80 code-point tại ranh giới
 * câu/dấu phẩy (SPEC-THE §2). THUẬT TOÁN ≥ bản FE clip80 (utils/shareCard.js):
 *   1) ≤max ký tự → nguyên văn (trần là ≤, không phải <);
 *   2) dấu câu CUỐI CÙNG tại index ≤ max-1 → giữ nó (FE quét từ i=max → worst
 *      case trả max+1 ký, vượt trần; BE đảm bảo ≤max tuyệt đối);
 *   3) không có dấu câu vừa → khoảng trắng cuối ≤max (ranh giới từ Việt,
 *      KHÔNG cắt giữa từ);
 *   4) hết cách (1 từ dính dài >max) → null = caller suy ra thẻ tối giản E6.
 * PUNCT là SIÊU TẬP của FE: thêm 2 dấu Hán 。 ，(hook chứa chữ Hán không vỡ).
 * Code-point-safe 100% qua mb_* — tiếng Việt precomposed + chữ Hán đa-byte.
 * Thuần function, không facade/HTTP — pattern ShareToken/Calendar.
 */
final class HookClip
{
    /** SPEC-THE §2: trần hook hiển thị trên trang /s/ + OG meta. */
    public const MAX = 80;

    /** Ranh giới câu/dấu phẩy — FE ∪ {。，}. */
    public const PUNCT = [',', '.', ';', ':', '!', '?', '…', '。', '，'];

    /** @return string|null bản clipped ≤ $max, hoặc null khi E6 (không cắt nổi) */
    public static function clip(string $text, int $max = self::MAX): ?string
    {
        $s = trim($text);
        if ($s === '') {
            return null;
        }
        $len = mb_strlen($s);
        if ($len <= $max) {
            return $s;
        }

        // 1) dấu câu cuối cùng sao cho phần giữ (0..i) ≤ max → i ≤ max-1
        for ($i = $max - 1; $i >= 0; $i--) {
            if (in_array(mb_substr($s, $i, 1), self::PUNCT, true)) {
                return mb_substr($s, 0, $i + 1);
            }
        }

        // 2) ranh giới từ: khoảng trắng cuối ≤ max (giữ độ dài = index < max)
        for ($i = $max; $i > 0; $i--) {
            if (mb_substr($s, $i - 1, 1) === ' ') {
                return rtrim(mb_substr($s, 0, $i));
            }
        }

        // 3) E6 — một "từ" duy nhất dài hơn trần
        return null;
    }

    private function __construct()
    {
    }
}
