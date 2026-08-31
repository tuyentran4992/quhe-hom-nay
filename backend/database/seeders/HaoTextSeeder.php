<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * BE-3XU — seed 64×6 = 384 từ hào vào `hexagram_hao_texts` (02-db §4b + §9).
 *
 * Nguồn: backend/database/data/hao_texts.json — CON của Pinned Dataset
 *   /data/agents/be-dev/outbox/t_0967d50b/hao_texts_64.json (sha256
 *   b6216b49…, CẤM sửa). File chuẩn hóa sinh ra bởi prepare_data.php (commit
 *   kèm), đã áp 4 gate CEO: 兑→兊 id58 · 弊/碩/扛 3 ô latin · nhãn 64×6 theo
 *   hexagrams.lines · id15 鳴謙 giữ nguyên. Format: mảng 64 object
 *   {id, hao_texts:[{vi,hao,han,quoc_am,nghia}×6]}.
 *
 * Idempotent: updateOrInsert theo khóa kép (hexagram_id, vi); chạy 2 lần = 384
 * row nội dung y hệt. Cần HexagramSeeder chạy trước (FK).
 */
class HaoTextSeeder extends Seeder
{
    public const SOURCE = __DIR__.'/../../database/data/hao_texts.json';

    public const EXPECTED_SHA256 = '00a2d8ff7bf21559a2214fdca15d9b50f47d35cb5a537044359e3aa64ef65511';

    public function run(): void
    {
        $path = realpath(self::SOURCE) ?: self::SOURCE;

        if (!is_file($path)) {
            throw new \RuntimeException("Thiếu file seed từ hào: {$path} (BE-3XU)");
        }

        $raw = (string) file_get_contents($path);

        // Chốt bản chuẩn hóa đã audit — sửa tay file là méo sha, dừng seed.
        $sha = hash('sha256', $raw);
        if ($sha !== self::EXPECTED_SHA256) {
            throw new \RuntimeException(
                "hao_texts.json KHÔNG khớp sha256 chốt BE-3XU ({$sha}), dừng seed."
            );
        }

        $data = json_decode($raw, true);

        if (!is_array($data) || count($data) !== 64) {
            throw new \RuntimeException('hao_texts.json phải là mảng 64 object — dừng seed.');
        }

        $now = now();
        $rows = 0;

        foreach ($data as $hexagram) {
            $id = (int) $hexagram['id'];

            if (count($hexagram['hao_texts']) !== 6) {
                throw new \RuntimeException("Quẻ {$id}: thiếu/thừa hào (mong 6).");
            }

            foreach ($hexagram['hao_texts'] as $hao) {
                $key = ['hexagram_id' => $id, 'vi' => (int) $hao['vi']];

                foreach (['hao', 'han', 'quoc_am', 'nghia'] as $f) {
                    if (trim((string) ($hao[$f] ?? '')) === '') {
                        throw new \RuntimeException("Quẻ {$id} hào {$hao['vi']}: rỗng field {$f}.");
                    }
                }

                DB::table('hexagram_hao_texts')->updateOrInsert($key, [
                    'hao' => $hao['hao'],
                    'han' => $hao['han'],
                    'quoc_am' => $hao['quoc_am'],
                    'nghia' => $hao['nghia'],
                    'updated_at' => $now,
                ]);
                $rows++;
            }
        }

        DB::table('hexagram_hao_texts')->whereNull('created_at')->update(['created_at' => $now]);

        if ($rows !== 384 || DB::table('hexagram_hao_texts')->count() !== 384) {
            throw new \RuntimeException("Seed từ hào sai số lượng: {$rows} row (mong 384).");
        }
    }
}
