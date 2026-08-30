<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BE-2 — specs/1.mvp/02-db.md §5 `draws` (DDL nguyên trạng; C-01 nằm ở uq_draws_device_date).
 * BE-1 sở hữu POST /api/draws nhưng bảng này là prerequisite của ai_jobs (FK) + gate
 * "draw hôm nay hoặc hôm qua" của #5 — BE-2 tạo migration đúng spec; BE-1 chỉ thêm model/service.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE draws (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_id      CHAR(26)        NOT NULL,
  user_id        BIGINT UNSIGNED NULL,
  hexagram_id    TINYINT UNSIGNED NOT NULL,
  drawn_date     DATE            NOT NULL,
  lines_rolled   JSON            NOT NULL,
  changing_lines JSON            NULL,
  created_at     TIMESTAMP NULL,
  updated_at     TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_draws_device_date (device_id, drawn_date),
  KEY idx_draws_device (device_id, created_at),
  CONSTRAINT fk_draws_device FOREIGN KEY (device_id) REFERENCES devices(device_id),
  CONSTRAINT fk_draws_hex FOREIGN KEY (hexagram_id) REFERENCES hexagrams(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS draws');
    }
};
