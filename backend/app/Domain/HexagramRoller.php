<?php

namespace App\Domain;

/**
 * Luật gieo cỏ thi đơn giản hóa — specs/1.mvp/03-api.md §3 (BẤT BIẾN).
 * Mỗi hào (dưới→trên): r = random_int(1,100); r≤44 → 7 (dương tĩnh); r≤88 → 8 (âm tĩnh);
 * r≤94 → 9 (dương động); else → 6 (âm động).
 * Pure PHP: CSPRNG của PHP, không import facade/HTTP (kiến trúc 01 §2).
 */
final class HexagramRoller
{
    public const STATIC_YANG = 7;
    public const STATIC_YIN = 8;
    public const MOVING_YANG = 9;
    public const MOVING_YIN = 6;

    /** @return int[] độ dài 6, giá trị ∈ {6,7,8,9}, chỉ số 0 = hào dưới cùng */
    public function roll(): array
    {
        $lines = [];
        for ($i = 0; $i < 6; $i++) {
            $r = random_int(1, 100);
            $lines[] = match (true) {
                $r <= 44 => self::STATIC_YANG,
                $r <= 88 => self::STATIC_YIN,
                $r <= 94 => self::MOVING_YANG,
                default => self::MOVING_YIN,
            };
        }

        return $lines;
    }

    /** @param array $lines @return int[] vị trí 1-based mang giá trị 6 hoặc 9 */
    public function changingLines(array $lines): array
    {
        $changing = [];
        foreach ($lines as $idx => $v) {
            if ($v === self::MOVING_YIN || $v === self::MOVING_YANG) {
                $changing[] = $idx + 1;
            }
        }

        return $changing;
    }

    /**
     * Quẻ gốc: hào dương (7|9)=1, âm (6|8)=0 → bitmask 6 bit dưới→trên,
     * tra `hexagrams.lines` khớp 1-1 (64 pattern unique — SEED-01 đã verify).
     *
     * @param int[6] $lines @return int[6]
     */
    public function toBitmask(array $lines): array
    {
        return array_map(
            fn (int $v) => ($v === self::STATIC_YANG || $v === self::MOVING_YANG) ? 1 : 0,
            array_values($lines)
        );
    }
}
