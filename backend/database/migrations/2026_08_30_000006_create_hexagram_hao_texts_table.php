<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 02-db §4b (SPEC-3XU) — `hexagram_hao_texts`: 64×6 = 384 từ hào. BẢNG RIÊNG theo
 * ADR-001 của dev-lead (không phải cột JSON trong hexagrams). DDL y nguyên spec:
 * PK kép (hexagram_id, vi), FK → hexagrams(id). utf8mb4_unicode_ci là mặc định
 * connection — không override để khớp 7 bảng còn lại.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hexagram_hao_texts', function (Blueprint $table) {
            $table->unsignedTinyInteger('hexagram_id');
            $table->unsignedTinyInteger('vi');
            $table->string('hao', 12);
            $table->string('han', 255);
            $table->string('quoc_am', 500);
            $table->string('nghia', 1000);
            $table->timestamps();

            $table->primary(['hexagram_id', 'vi']);
            $table->foreign('hexagram_id')->references('id')->on('hexagrams');
        });
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS hexagram_hao_texts');
    }
};
