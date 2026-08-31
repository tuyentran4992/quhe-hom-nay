<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-3XU (01-overview §4bis) — "Quẻ biến vẫn tính + lưu DB (nghiên cứu sau)".
 * draws.bien_hexagram_id: id quẻ biến (flip các hào động) hoặc NULL khi
 * 0 hào động. KHÔNG trả qua API MVP, KHÔNG vào prompt AI-Box.
 * Kiểu khớp hexagrams.id (TINYINT UNSIGNED — FK MariaDB yêu cầu cùng số).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draws', function (Blueprint $table) {
            $table->unsignedTinyInteger('bien_hexagram_id')
                ->nullable()
                ->after('changing_lines')
                ->comment('id quẻ biến (NULL = 0 hào động); chỉ lưu, không lộ API MVP');
            $table->foreign('bien_hexagram_id')->references('id')->on('hexagrams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('draws', function (Blueprint $table) {
            $table->dropForeign(['bien_hexagram_id']);
            $table->dropColumn('bien_hexagram_id');
        });
    }
};
