<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ROUTER-FMT (card t_18927e08) — ai_jobs.router_category: lưu NGUYÊN TOKEN kết
 * quả router (1 trong 11 domain RouterPrompt::DOMAINS, kể cả UNCLEAR) làm dữ
 * liệu phân loại cho báo cáo. Cột topic ENUM 3 tab GIỮ NGUYÊN — entitlement
 * không đổi ngữ nghĩa (§5.3 LUAN-V3). Không backfill: job cũ = NULL (trước router-fmt
 * không đo được). Job tab thuần không question: worker ghi thẳng domain tương
 * ứng tab (tinh_duyen/tai_loc/cong_viec). Router LỖI → NULL (không suy diễn).
 * DDL Blueprint (schema-agnostic) vì cột này test chạy cả sqlite (A4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_jobs', function (Blueprint $table) {
            $table->string('router_category', 20)->nullable()->after('question');
        });
    }

    public function down(): void
    {
        Schema::table('ai_jobs', function (Blueprint $table) {
            $table->dropColumn('router_category');
        });
    }
};
