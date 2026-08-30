<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * SEED-01: chỉ seed danh mục hexagrams (64 quẻ).
 * specs/02-db.md §9: KHÔNG seed users/devices/draws/payments/ai_jobs — runtime data.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(HexagramSeeder::class);
    }
}
