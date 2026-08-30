<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * SEED-01 — seed 64 quẻ vào bảng `hexagrams` theo contract specs/02-db.md §9.
 *
 * Nguồn BẤT BIẾN: backend/database/data/hexagrams.json
 *   sha256 76cfc11f5e825f3aa0c98530e45995d488bea685977231e74212c09d2dfc5d4f
 *   64 object, id 1..64, đã strip canSoi (0 lần xuất hiện) — seeder CẤM thêm lại.
 *
 * Key camelCase → cột snake_case (§9):
 *   quocAm→quoc_am, daiCI→dai_ci, free→free_content, vvanNien→vv_nien,
 *   banGoc→ban_goc, luanNay→luan_nay; lines/keywords/cat giữ nguyên JSON.
 * Idempotent: updateOrInsert theo `id`; chạy 2 lần = 64 row, nội dung y hệt.
 */
class HexagramSeeder extends Seeder
{
    /** Đường dẫn file nguồn đã khóa (tương đối gốc repo → không phụ thuộc cwd). */
    public const SOURCE = __DIR__.'/../../database/data/hexagrams.json';

    public const EXPECTED_SHA256 = '76cfc11f5e825f3aa0c98530e45995d488bea685977231e74212c09d2dfc5d4f';

    public function run(): void
    {
        $path = realpath(self::SOURCE) ?: self::SOURCE;

        if (!is_file($path)) {
            throw new \RuntimeException("Thiếu file seed 64 quẻ: {$path} (SEED-01)");
        }

        $raw = (string) file_get_contents($path);

        // Chốt nguồn khóa: file repo phải là bản nocansoi đã audit, không phải bản chế lại.
        $sha = hash('sha256', $raw);
        if ($sha !== self::EXPECTED_SHA256) {
            throw new \RuntimeException(
                'hexagrams.json trong repo KHÔNG khớp sha256 khóa SEED-01 ('.$sha.'), dừng seed.'
            );
        }
        if (str_contains($raw, 'canSoi')) {
            throw new \RuntimeException('File seed chứa canSoi — sai bản khóa nocansoi, dừng seed.');
        }

        $items = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($items) || !array_is_list($items) || count($items) !== 64) {
            throw new \RuntimeException('File seed phải là array 64 object, đếm được: '
                .(is_array($items) ? count($items) : 'n/a'));
        }

        $now = now();

        foreach ($items as $item) {
            $this->assertShape($item);

            $id = (int) $item['id'];

            DB::table('hexagrams')->updateOrInsert(
                ['id' => $id],
                [
                    'han' => $item['han'],
                    'ten' => $item['ten'],
                    'quoc_am' => $item['quocAm'],
                    'upper' => $item['upper'],
                    'lower' => $item['lower'],
                    'lines' => json_encode($item['lines'], JSON_UNESCAPED_UNICODE),
                    'symbol' => $item['symbol'],
                    'dai_ci' => $item['daiCI'],
                    'free_content' => json_encode($item['free'], JSON_UNESCAPED_UNICODE),
                    'keywords' => json_encode($item['keywords'], JSON_UNESCAPED_UNICODE),
                    'vv_nien' => $item['vvanNien'],
                    'cat' => json_encode($item['cat'], JSON_UNESCAPED_UNICODE),
                    'ban_goc' => json_encode($item['banGoc'], JSON_UNESCAPED_UNICODE),
                    'luan_nay' => $item['luanNay'],
                    'updated_at' => $now,
                ]
            );
        }

        // created_at chỉ cho row MỚI (row cũ giữ nguyên timestamp → chạy 2 lần bảng y hệt).
        DB::table('hexagrams')->whereNull('created_at')->update(['created_at' => $now]);

        // Assertion sau seed theo §9 — fail loud thay vì âm thầm thiếu row.
        $count = DB::table('hexagrams')->count();
        $sum = (int) DB::table('hexagrams')->sum('id');
        if ($count !== 64 || $sum !== 2080) {
            throw new \RuntimeException("Post-seed assert fail: count={$count} sum={$sum} (kỳ vọng 64/2080)");
        }
    }

    /**
     * Validate ranh giới từng item trước khi ghi DB (input file = hàng độc).
     */
    private function assertShape(array $item): void
    {
        $id = $item['id'] ?? '?';
        foreach (['id', 'han', 'ten', 'quocAm', 'upper', 'lower', 'lines', 'symbol',
            'daiCI', 'free', 'keywords', 'vvanNien', 'cat', 'banGoc', 'luanNay'] as $k) {
            if (!array_key_exists($k, $item)) {
                throw new \RuntimeException("Item quẻ id={$id} thiếu khóa {$k}");
            }
        }
        if ((int) $id < 1 || (int) $id > 64) {
            throw new \RuntimeException("Quẻ id={$id} ngoài khoảng 1..64");
        }
        if (!is_array($item['lines']) || count($item['lines']) !== 6
            || count(array_diff($item['lines'], [0, 1])) > 0) {
            throw new \RuntimeException("Quẻ id={$id}: lines phải là 6 số 0/1");
        }
        if (is_array($item['keywords']) && count($item['keywords']) !== 4) {
            throw new \RuntimeException("Quẻ id={$id}: keywords phải đúng 4 phần tử");
        }
        foreach (['congViec', 'tinhDuyen', 'taiLoc'] as $slot) {
            if (!isset($item['free'][$slot]) || !is_string($item['free'][$slot])) {
                throw new \RuntimeException("Quẻ id={$id}: free.{$slot} missing");
            }
        }
        if (str_contains(json_encode($item, JSON_UNESCAPED_UNICODE), 'canSoi')) {
            throw new \RuntimeException("Quẻ id={$id} còn canSoi — nguồn sai bản");
        }
    }
}
