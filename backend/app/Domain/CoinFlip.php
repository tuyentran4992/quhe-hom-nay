<?php

namespace App\Domain;

/**
 * BE-3XU (C-09, 03-api §3.1) — một hào = 3 đồng xu ĐỘC LẬP: sấp=2, ngửa=3,
 * tổng ∈ {6,7,8,9} với xác suất 12.5/37.5/37.5/12.5. CSPRNG `random_int`,
 * CẤM rand()/mt_rand(), cấm bảng tra "dịch" phân phối.
 * Pure PHP, 1 trách nhiệm duy nhất (chống god class) — HexagramRoller ghép qua đây.
 */
final class CoinFlip
{
    /**
     * Bảng đối chiếu tổ hợp (§3.1): 222→6 | 223/232/322→7 | 332/323/233→8 | 333→9.
     * Static thuần để unit test liệt kê đủ 8 mẫu equiprobable.
     */
    public static function lineValue(int $a, int $b, int $c): int
    {
        foreach ([$a, $b, $c] as $face) {
            if ($face !== 2 && $face !== 3) {
                throw new \RuntimeException("mặt xu không hợp lệ: $face");
            }
        }

        return $a + $b + $c;
    }

    /** Gieo 1 hào thật: 3 lần random_int(2,3) ĐỘC LẬP, không reuse kết quả. */
    public static function flipLine(): int
    {
        return self::lineValue(random_int(2, 3), random_int(2, 3), random_int(2, 3));
    }
}
