<?php

namespace Tests\Unit\Domain;

use App\Domain\HexagramRoller;
use Tests\TestCase;

/**
 * U4 (specs/1.mvp/05-testplan.md §1) — luật gieo cỏ thi đơn giản hóa (03-api §3).
 * Pure unit: không DB — tự đối chiếu 64 pattern bằng chính bảng tra đã nhồi từ seed json.
 */
class HexagramRollerTest extends TestCase
{
    private function patterns(): array
    {
        $rows = json_decode(
            (string) file_get_contents(base_path('database/data/hexagrams.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return array_map(fn ($r) => $r['lines'], $rows);
    }

    public function test_every_value_is_within_6789_and_changing_lines_match(): void
    {
        $roller = new HexagramRoller();

        for ($i = 0; $i < 10000; $i++) {
            $lines = $roller->roll();
            $this->assertCount(6, $lines);
            foreach ($lines as $pos => $v) {
                $this->assertContains($v, [6, 7, 8, 9], "hào {$pos} ngoài {6,7,8,9}");
            }
            $expectedChanging = [];
            foreach ($lines as $idx => $v) {
                if ($v === 6 || $v === 9) {
                    $expectedChanging[] = $idx + 1; // 1-based
                }
            }
            $this->assertSame($expectedChanging, $roller->changingLines($lines));
        }
    }

    public function test_moving_line_share_is_about_12_percent(): void
    {
        $roller = new HexagramRoller();
        $moving = 0;
        $total = 0;

        for ($i = 0; $i < 10000; $i++) {
            foreach ($roller->roll() as $v) {
                $total++;
                if ($v === 6 || $v === 9) {
                    $moving++;
                }
            }
        }
        $share = $moving / $total;
        // 6% + 6% = 12%; cửa sổ 9–15% theo 05-testplan U4
        $this->assertGreaterThan(0.09, $share);
        $this->assertLessThan(0.15, $share);
    }

    public function test_bitmask_always_matches_exactly_one_of_64_patterns(): void
    {
        $roller = new HexagramRoller();
        $patterns = $this->patterns();
        $this->assertCount(64, $patterns);

        for ($i = 0; $i < 10000; $i++) {
            $lines = $roller->roll();
            $bitmask = $roller->toBitmask($lines);
            $hits = array_values(array_filter(
                $patterns,
                fn ($p) => array_map(intval(...), $p) === $bitmask
            ));
            $this->assertCount(1, $hits, 'quẻ ảo — pattern không khớp 1-1 bảng hexagrams');
        }
    }

    public function test_bitmask_maps_yang_lines_to_one_bottom_up(): void
    {
        $roller = new HexagramRoller();
        $this->assertSame([1, 0, 1, 1, 0, 0], $roller->toBitmask([7, 8, 9, 7, 6, 8]));
    }
}
