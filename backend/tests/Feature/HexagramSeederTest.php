<?php

namespace Tests\Feature;

use Database\Seeders\HexagramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SEED-01 — specs/02-db.md §4 + §9, test plan specs/05-testplan.md U1–U3.
 * Nguồn: backend/database/data/hexagrams.json (sha256 khóa 76cfc11f...).
 */
class HexagramSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedNow(): void
    {
        (new HexagramSeeder())->run();
    }

    public function test_seed_file_in_repo_matches_locked_sha256_and_has_no_canSoi(): void
    {
        $raw = file_get_contents(HexagramSeeder::SOURCE);
        $this->assertSame(
            '76cfc11f5e825f3aa0c98530e45995d488bea685977231e74212c09d2dfc5d4f',
            hash('sha256', $raw),
            'File seed trong repo phải KHỚP tuyệt đối nguồn khóa CEO — cấm chế lại.'
        );
        $this->assertSame(0, substr_count($raw, 'canSoi'));
    }

    public function test_u1_seed_loads_exactly_64_unique_ids_1_to_64(): void
    {
        $this->seedNow();
        $this->assertSame(64, DB::table('hexagrams')->count());
        $this->assertSame(2080, (int) DB::table('hexagrams')->sum('id'));
        $ids = DB::table('hexagrams')->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all();
        $this->assertSame(range(1, 64), $ids);
    }

    public function test_u1_seed_is_idempotent_second_run_keeps_64_identical_rows(): void
    {
        $this->seedNow();
        $first = DB::table('hexagrams')->orderBy('id')->get()
            ->map(fn ($r) => (array) $r)->all();
        $this->seedNow();
        $this->assertSame(64, DB::table('hexagrams')->count());
        $second = DB::table('hexagrams')->orderBy('id')->get()
            ->map(fn ($r) => (array) $r)->all();
        // nội dung phải y hệt (chỉ updated_at được phép refresh)
        $strip = fn (array $rows) => array_map(function (array $r) {
            unset($r['updated_at']);

            return $r;
        }, $rows);
        $this->assertSame($strip($first), $strip($second));
    }

    public function test_u1_row_id1_is_can_vi_thien(): void
    {
        $this->seedNow();
        $row = DB::table('hexagrams')->where('id', 1)->first();
        $this->assertNotNull($row);
        $this->assertSame('Càn Vi Thiên', $row->ten);
        $this->assertSame('乾', $row->han);
        $this->assertSame([1, 1, 1, 1, 1, 1], json_decode($row->lines, true));
        $this->assertSame('䷀', $row->symbol);
        $this->assertSame('Càn', $row->upper);
        $this->assertSame('Càn', $row->lower);
    }

    public function test_u2_no_canSoi_key_or_value_in_any_json_column(): void
    {
        $this->seedNow();
        $jsonCols = ['lines', 'free_content', 'keywords', 'cat', 'ban_goc'];
        $like = implode(' OR ', array_map(
            fn ($c) => "CAST(`{$c}` AS CHAR) LIKE '%canSoi%'",
            $jsonCols
        ));
        $hits = (int) DB::selectOne("SELECT COUNT(*) AS c FROM hexagrams WHERE {$like}")->c;
        $this->assertSame(0, $hits, 'Seeder không được thêm lại canSoi (spec §4).');
    }

    public function test_u3_free_content_slots_within_cut45(): void
    {
        $this->seedNow();
        foreach (DB::table('hexagrams')->get() as $row) {
            $free = json_decode($row->free_content, true);
            foreach (['congViec', 'tinhDuyen', 'taiLoc'] as $slot) {
                $this->assertArrayHasKey($slot, $free, "row {$row->id} thiếu slot {$slot}");
                $n = count(preg_split('/\s+/u', trim($free[$slot]), -1, PREG_SPLIT_NO_EMPTY));
                $this->assertLessThanOrEqual(45, $n, "row {$row->id} slot {$slot} = {$n} từ > CUT-45");
            }
        }
    }

    public function test_all_columns_populated_with_camel_to_snake_mapping(): void
    {
        $this->seedNow();
        $cols = ['han', 'ten', 'quoc_am', 'upper', 'lower', 'lines', 'symbol', 'dai_ci',
            'free_content', 'keywords', 'vv_nien', 'cat', 'ban_goc', 'luan_nay'];
        foreach (DB::table('hexagrams')->orderBy('id')->get() as $row) {
            foreach ($cols as $c) {
                $this->assertNotSame('', (string) $row->$c, "row {$row->id} cột {$c} rỗng");
            }
            $this->assertCount(4, json_decode($row->keywords, true), "row {$row->id} keywords != 4");
            $this->assertCount(6, json_decode($row->lines, true), "row {$row->id} lines != 6");
            $this->assertCount(1, json_decode($row->cat, true), "row {$row->id} cat != 1 phần tử");
            $this->assertArrayHasKey('quaTu', json_decode($row->ban_goc, true), "row {$row->id} ban_goc.thoanTruyen? không — thiếu quaTu");
        }
        // dungHao chỉ có ở id 1, 2 theo spec §4
        $bg63 = json_decode(DB::table('hexagrams')->where('id', 63)->value('ban_goc'), true);
        $this->assertArrayNotHasKey('dungHao', $bg63);
        $bg1 = json_decode(DB::table('hexagrams')->where('id', 1)->value('ban_goc'), true);
        $this->assertArrayHasKey('dungHao', $bg1);
    }
}
