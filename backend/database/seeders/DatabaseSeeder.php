<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * BE-0 scaffold: seeder mặc định Laravel, để trống có chủ đích (no-op).
 * SEED-01 (card/t_fdb90b30) đã có bản riêng gọi HexagramSeeder + sha256-lock —
 * BE-0 KHÔNG dup seeder nghiệp vụ: bảng hexagrams là migration của SEED-01,
 * gọi HexagramSeeder ở đây = migrate --seed chết vì thiếu bảng.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // User::factory(10)->create();
    }
}
