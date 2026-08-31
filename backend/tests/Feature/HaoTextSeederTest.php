<?php

namespace Tests\Feature;

use Database\Seeders\HaoTextSeeder;
use Database\Seeders\HexagramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BE-3XU — testplan U7: HaoTextSeeder nạp đúng 64×6=384 từ hào, không ô rỗng,
 * nhãn khớp âm dương, id15 vi6 nguyên văn 鳴謙，利用行師，征邑國。 (CEO đã kiểm
 * tận nguồn b6216b49), và idempotent. Nguồn: database/data/hao_texts.json
 * (con của Pinned Dataset — script chuẩn hóa prepare_data.php tạo ra, file nguồn
 * outbox t_0967d50b CẤM sửa).
 */
class HaoTextSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedNow(): void
    {
        (new HexagramSeeder())->run(); // FK hexagram_id
        (new HaoTextSeeder())->run();
    }

    public function test_u7_seed_exactly_384_rows_no_empty_cell(): void
    {
        $this->seedNow();
        $this->assertSame(384, DB::table('hexagram_hao_texts')->count());
        $this->assertSame(0, (int) DB::table('hexagram_hao_texts')
            ->where(fn ($q) => $q->where('han', '')->orWhere('quoc_am', '')->orWhere('nghia', ''))
            ->count(), 'Có ô rỗng han/quoc_am/nghia.');
        // mỗi quẻ đúng 6 vi 1..6
        $bad = DB::table('hexagram_hao_texts')
            ->select('hexagram_id')
            ->groupBy('hexagram_id')
            ->havingRaw('COUNT(*) <> 6 OR MIN(vi) <> 1 OR MAX(vi) <> 6')
            ->get();
        $this->assertCount(0, $bad, 'Quẻ thiếu vi: ' . json_encode($bad));
    }

    public function test_u7_id15_vi6_matches_pinned_source_verbatim(): void
    {
        $this->seedNow();
        $row = DB::table('hexagram_hao_texts')
            ->where('hexagram_id', 15)->where('vi', 6)->first();
        $this->assertNotNull($row);
        // CEO kiểm: "id15 hàng cuối = 鳴謙，利用行師，征邑國。 khớp lệnh" — nguồn
        // lưu nguyên văn kèm tiền tố hào ("上六：…"), so phần lõi.
        $this->assertStringContainsString('鳴謙，利用行師，征邑國。', $row->han);
        $this->assertStringStartsWith('上六：', $row->han);
    }

    public function test_u7_label_matches_yin_yang_of_hexagram_lines(): void
    {
        $this->seedNow();
        $hexagrams = DB::table('hexagrams')->get();
        // Tập nhãn chính tắc 02-db §4b (hoa riêng chữ đầu, theo mẫu 03-api §2).
        $expected = [
            1 => ['Sơ cửu', 'Cửu nhị', 'Cửu tam', 'Cửu tứ', 'Cửu ngũ', 'Thượng cửu'],
            0 => ['Sơ lục', 'Lục nhị', 'Lục tam', 'Lục tứ', 'Lục ngũ', 'Thượng lục'],
        ];
        foreach ($hexagrams as $hx) {
            $lines = json_decode($hx->lines, true);
            for ($vi = 1; $vi <= 6; $vi++) {
                $label = DB::table('hexagram_hao_texts')
                    ->where('hexagram_id', $hx->id)->where('vi', $vi)->value('hao');
                $yang = $lines[$vi - 1] === 1;
                $this->assertSame(
                    $expected[$yang ? 1 : 0][$vi - 1],
                    $label,
                    "nhãn '{$label}' sai âm dương (hex {$hx->id} vi {$vi} lines {$lines[$vi - 1]})"
                );
            }
        }
    }

    public function test_seed_is_idempotent(): void
    {
        $this->seedNow();
        $first = DB::table('hexagram_hao_texts')->orderBy('hexagram_id')->orderBy('vi')->get()
            ->map(fn ($r) => (array) $r)->all();
        (new HaoTextSeeder())->run();
        $this->assertSame(384, DB::table('hexagram_hao_texts')->count());
        $second = DB::table('hexagram_hao_texts')->orderBy('hexagram_id')->orderBy('vi')->get()
            ->map(fn ($r) => (array) $r)->all();
        $strip = fn (array $rows) => array_map(function (array $r) {
            unset($r['created_at'], $r['updated_at']);

            return $r;
        }, $rows);
        $this->assertSame($strip($first), $strip($second));
    }

    public function test_van_gate_cells_are_normalized(): void
    {
        // 3 vụn gate CEO (id18.5 tệ / id23.6 thạc / id28.3 gồng) + 兑→兊 id58.
        $this->seedNow();
        $get = fn ($id, $vi) => DB::table('hexagram_hao_texts')
            ->where('hexagram_id', $id)->where('vi', $vi)->first();
        $this->assertStringNotContainsString('弊', $get(18, 5)->nghia);
        $this->assertStringContainsString('tệ', $get(18, 5)->nghia);
        $this->assertStringNotContainsString('碩', $get(23, 6)->quoc_am);
        $this->assertStringContainsString('thạc', $get(23, 6)->quoc_am);
        $this->assertStringNotContainsString('扛', $get(28, 3)->quoc_am);
        // id58: quẻ tên Khôn đổi 5151→514A ở chữ đầu han các hào (gate A của CEO)
        $r58 = DB::table('hexagram_hao_texts')->where('hexagram_id', 58)->get();
        foreach ($r58 as $row) {
            $this->assertStringNotContainsString('兑', $row->han, 'id58 chưa đổi 兑→兊');
        }
    }
}