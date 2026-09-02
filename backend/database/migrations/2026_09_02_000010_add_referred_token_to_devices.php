<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * VS1-L1 (SPEC-VS1-L1 §2) — 1 migration DUY NHẤT: devices.referred_token
 * first-touch-khóa token thẻ nguồn, capture tại CTA `/s/{token}/cta`.
 * - CHAR(10) khớp ShareToken::isValid ^[0-9A-Za-z]{10}$; KHÔNG phải FK —
 *   cột là dấu vết attribution thô, token có thể失效 nếu share_links đổi.
 * - idx_devices_referred_token phục vụ GROUP BY của Q3 (bản cột, §4).
 * - Không backfill: prod = 0 device (KPI-W1 §0); device share-referred đời
 *   trước để NULL → query §4 gộp vào nhóm "token NULL" — đúng bản chất
 *   "trước L1 không đo được từ thẻ nào". DDL raw SQL theo lệ 000007.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE devices
  ADD COLUMN referred_token CHAR(10) NULL AFTER utm_campaign,
  ADD KEY idx_devices_referred_token (referred_token)
SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE devices DROP COLUMN referred_token');
    }
};
