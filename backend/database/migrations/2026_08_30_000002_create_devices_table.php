<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BE-2 — specs/1.mvp/02-db.md §2 `devices` (DDL nguyên trạng).
 * Device = danh tính chính, gốc của mọi entitlement (02-db §8).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE devices (
  device_id   CHAR(26)        NOT NULL,
  first_seen  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  session_id  VARCHAR(255)    NULL,
  PRIMARY KEY (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS devices');
    }
};
