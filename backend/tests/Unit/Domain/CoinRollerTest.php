<?php

namespace Tests\Unit\Domain;

use App\Domain\CoinFlip;
use App\Domain\HexagramRoller;
use RuntimeException;

/**
 * BE-3XU (C-09, 03-api §3.1) — roller 3 ĐỒNG XU chuẩn, thay cỏ-thi 44/88/94 cũ.
 * Pure unit: không DB, không HTTP.
 *  - tổ hợp equiprobable 8 mẫu: 222→6, {223,232,322}→7, {332,323,233}→8, 333→9;
 *  - roll() dùng CSPRNG (random_int) — mock qua seed hook của CoinFlip để test bảng;
 *  - API cũ changingLines()/toBitmask() giữ nguyên hợp đồng.
 */
class CoinRollerTest extends \PHPUnit\Framework\TestCase
{
    private HexagramRoller $roller;

    protected function setUp(): void
    {
        $this->roller = new HexagramRoller();
    }

    /** Bảng tổ hợp 3 xu → giá trị hào (§3.1, liệt kê kiểm chứng đủ 8 mẫu). */
    public function test_coin_sum_mapping(): void
    {
        $this->assertSame(6, CoinFlip::lineValue(2, 2, 2));
        $this->assertSame(7, CoinFlip::lineValue(2, 2, 3));
        $this->assertSame(7, CoinFlip::lineValue(2, 3, 2));
        $this->assertSame(7, CoinFlip::lineValue(3, 2, 2));
        $this->assertSame(8, CoinFlip::lineValue(3, 3, 2));
        $this->assertSame(8, CoinFlip::lineValue(3, 2, 3));
        $this->assertSame(8, CoinFlip::lineValue(2, 3, 3));
        $this->assertSame(9, CoinFlip::lineValue(3, 3, 3));
    }

    public function test_coin_sum_rejects_bad_face(): void
    {
        $this->expectException(RuntimeException::class);
        CoinFlip::lineValue(2, 2, 4);
    }

    /** roll() trả đúng 6 hào ∈ {6,7,8,9}. */
    public function test_roll_shape(): void
    {
        foreach (range(1, 200) as $_) {
            $lines = $this->roller->roll();
            $this->assertCount(6, $lines);
            foreach ($lines as $v) {
                $this->assertContains($v, [6, 7, 8, 9]);
            }
        }
    }

    /**
     * Phân phối 3 xu chuẩn (§3.1/C-09) — ±5σ trên ≥200k hào (01-overview §4bis/
     * acceptance #2). 18 lần random_int độc lập / quẻ (3 xu × 6 hào).
     * Chạy thật, không mock — CSPRNG phải ra đúng xác suất 12.5/37.5/37.5/12.5.
     */
    public function test_distribution_matches_three_coin_model(): void
    {
        $n = 200_000;
        $count = [6 => 0, 7 => 0, 8 => 0, 9 => 0];
        for ($i = 0; $i < $n; $i++) {
            $count[$this->roller->rollOneLine()]++;
        }
        $p = [6 => 0.125, 7 => 0.375, 8 => 0.375, 9 => 0.125];
        $report = [];
        foreach ($p as $v => $prob) {
            $mu = $n * $prob;
            $sigma = sqrt($n * $prob * (1 - $prob));
            $dev = abs($count[$v] - $mu) / $sigma;
            $report[] = sprintf('value %d: quan_sat=%.4f ky_vong=%.4f lech=%.2f sigma', $v, $count[$v] / $n, $prob, $dev);
            $this->assertLessThanOrEqual(5.0, $dev, "lệch >5σ ở giá trị $v:\n" . implode("\n", $report));
        }
        fwrite(STDERR, "\n[BE-3XU] bảng phân phối $n mẫu:\n  " . implode("\n  ", $report) . "\n");
    }

    /** changing_lines: chỉ 6/9, 1-based, thứ tự sơ→thượng. */
    public function test_changing_lines_mapping(): void
    {
        $this->assertSame([3, 6], $this->roller->changingLines([7, 8, 9, 8, 7, 6]));
        $this->assertSame([1, 2, 3, 4, 5, 6], $this->roller->changingLines([9, 6, 9, 6, 9, 6]));
        $this->assertSame([], $this->roller->changingLines([7, 8, 7, 8, 7, 8]));
    }

    /** quẻ gốc bitmask: dương (7|9)=1, âm (6|8)=0 — dưới→trên. */
    public function test_to_bitmask(): void
    {
        $this->assertSame([1, 0, 1, 0, 1, 0], $this->roller->toBitmask([7, 8, 9, 6, 7, 8]));
    }
}
