<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LUAN-V2 (SPEC-LUAN-V2 §4.2, card t_c86f3954) — câu hỏi khách gắn với job luận,
 * không gắn với phiên gieo (1 draw luận nhiều topic, mỗi lần hỏi khác).
 * NULL = "không hỏi" = job cũ (không backfill — job cũ đúng ngữ nghĩa question NULL).
 * Không index mới: cache lookup đi theo (status, topic, draw-in-subquery) đã nhỏ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_jobs', function (Blueprint $table) {
            $table->string('question', 200)->nullable()->after('topic');
        });
    }

    public function down(): void
    {
        Schema::table('ai_jobs', function (Blueprint $table) {
            $table->dropColumn('question');
        });
    }
};
