<?php

namespace Tests\Unit\Domain;

use App\Domain\HexagramRoller;
use RuntimeException;

/**
 * BE-3XU S2/S3 — "luật luận" §4bis trên DOMAIN (pure, không DB):
 *  - replay deterministic: lines_rolled → bitmask gốc không random lại (02-db §4b),
 *  - quẻ biến: bitmask gốc XOR các hào động → pattern quẻ biến (§4bis),
 *  - lookup 64 pattern từ database/data/hexagrams.json.
 * API cũ roll()/changingLines()/toBitmask() giữ nguyên hợp đồng (gate t_04394e77).
 */
class HexagramBienTest extends \PHPUnit\Framework\TestCase
{
    private HexagramRoller $roller;

    protected function setUp(): void
    {
        $this->roller = new HexagramRoller();
    }

    /** replay: cùng lines_rolled => cùng changingLines + cùng bitmask, lặp 1000 lần. */
    public function test_replay_deterministic(): void
    {
        $lines = [8, 9, 7, 6, 7, 7];
        $changing = $this->roller->changingLines($lines);
        $mask = $this->roller->toBitmask($lines);
        for ($i = 0; $i < 1000; $i++) {
            $this->assertSame($changing, $this->roller->changingLines($lines));
            $this->assertSame($mask, $this->roller->toBitmask($lines));
        }
    }

    /** changing_lines: chỉ 6/9, vị trí 1-based, thứ tự sơ→thượng. */
    public function test_changing_lines_mapping(): void
    {
        $this->assertSame([3, 6], $this->roller->changingLines([7, 8, 9, 8, 7, 6]));
        $this->assertSame([1, 2, 3, 4, 5, 6], $this->roller->changingLines([9, 6, 9, 6, 9, 6]));
        $this->assertSame([], $this->roller->changingLines([7, 8, 7, 8, 7, 8]));
    }

    /** bienOf: đảo ĐÚNG các hào động, hào tĩnh giữ nguyên. */
    public function test_bien_of_flips_only_changing(): void
    {
        $mask = [1, 0, 1, 1, 0, 0]; // gốc (dưới→trên)
        $bien = $this->roller->bienOf($mask, [2, 5]);
        $this->assertSame([1, 1, 1, 1, 1, 0], $bien);
        $this->assertSame($mask, $this->roller->bienOf($mask, []), '0 hào động -> quẻ biến = quẻ gốc');
    }

    /** lookup: cả 64 pattern trong hexagrams.json tra được, khớp id + lines. */
    public function test_find_pattern_covers_all_64(): void
    {
        $all = json_decode((string) file_get_contents(__DIR__ . '/../../../database/data/hexagrams.json'), true);
        $this->assertCount(64, $all);
        foreach ($all as $hx) {
            $binary = array_map(intval(...), $hx['lines']);
            $found = $this->roller->findPattern($binary);
            $this->assertNotNull($found, "pattern id {$hx['id']} phải tra được");
            $this->assertSame((int) $hx['id'], (int) $found['id']);
            $this->assertSame($binary, array_map(intval(...), $found['lines']));
        }
    }

    /** Case §4bis: Càn (id1) lines_rolled có hào 2 động -> quẻ biến = id13 同人 (Thiên Hỏa Đồng Nhân). */
    public function test_bien_case_gan(): void
    {
        $linesRolled = [7, 9, 7, 7, 7, 7]; // Càn, hào 2 động
        $this->assertSame([2], $this->roller->changingLines($linesRolled));
        $goc = $this->roller->toBitmask($linesRolled);
        $bien = $this->roller->bienOf($goc, $this->roller->changingLines($linesRolled));
        $hx = $this->roller->findPattern($bien);
        $this->assertNotNull($hx);
        $this->assertSame(13, (int) $hx['id'], 'q1 hào 2 động -> 同人 (id13)');
    }

    /** input xấu phải bị chặn ở đường mới (validation ranh giới, API cũ giữ nguyên). */
    public function test_roll_from_validates(): void
    {
        $this->expectException(RuntimeException::class);
        $this->roller->rollFrom([7, 8, 7, 8, 7]); // 5 dòng
    }

    public function test_roll_from_validates_values(): void
    {
        $this->expectException(RuntimeException::class);
        $this->roller->rollFrom([7, 8, 5, 8, 7, 8]); // 5 không phải giá trị gieo
    }

    public function test_bien_of_rejects_out_of_range_position(): void
    {
        $this->expectException(RuntimeException::class);
        $this->roller->bienOf([1, 1, 1, 1, 1, 1], [7]);
    }
}
