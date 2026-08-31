<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MKT-F2 — specs/1.mvp/06-mkt-tracking.md §2: 1 migration DUY NHẤT cho cả
 * utm_* trên devices + bảng events (DDL nguyên trang spec, không tự đổi contract).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE devices
  ADD COLUMN utm_source   VARCHAR(100) NULL AFTER session_id,
  ADD COLUMN utm_medium   VARCHAR(100) NULL,
  ADD COLUMN utm_campaign VARCHAR(100) NULL
SQL);

        DB::statement(<<<'SQL'
CREATE TABLE events (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_id  CHAR(26)        NOT NULL,
  name       VARCHAR(50)     NOT NULL,
  props      JSON            NULL,
  created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_events_device_name (device_id, name),
  KEY idx_events_name_created (name, created_at),
  CONSTRAINT fk_events_device FOREIGN KEY (device_id) REFERENCES devices(device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS events');
        DB::statement('ALTER TABLE devices DROP COLUMN utm_source, DROP COLUMN utm_medium, DROP COLUMN utm_campaign');
    }
};
