<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QUOTA-N/Q2 (card t_1b5a0c23, D3) — ai_jobs.from_cache: đánh dấu job SINH RA TỪ
 * BÀI CŨ (cache hit / paraphrase judge DU_GIONG trả bài cũ — card Q3) để cổng
 * quota "N lượt luận sâu THẬT / quẻ" đếm ĐÚNG: `status=done AND from_cache=0`.
 *
 * Khảo sát hiện trạng (ghi cả vào PROGRESS.md): đường cache-hit "tạo job done tại
 * chỗ" của AC-2 ĐÃ BỊ REVIEW-LUAN (t_8aa93a01) thay bằng 409 AI_ALREADY_DONE +
 * #5b đọc lại — mọi row done hiện hữu đều là bài THAT (1 done = 1 provider call),
 * result_key_hash chỉ phục vụ idempotency so-body, KHÔNG đủdacdiem phân biệt
 * row that vs cache cho tương lai (Q3 sẽ sinh job done tái-sử-dụng kết quả).
 * → chọn phương án migration theo card, default false = toàn bộ row cũ là that.
 *
 * Blueprint thuần (schema-agnostic, học lệ 000011): chạy sạch cả mysql + sqlite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_jobs', function (Blueprint $table) {
            $table->boolean('from_cache')->default(false)->after('router_category');
        });
    }

    public function down(): void
    {
        Schema::table('ai_jobs', function (Blueprint $table) {
            $table->dropColumn('from_cache');
        });
    }
};
