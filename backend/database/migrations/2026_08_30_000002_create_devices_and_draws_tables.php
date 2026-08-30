<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BE-1 — `devices` (02-db §2) + `draws` (02-db §5) dựng Y NGUYÊN DDL specs/1.mvp/02-db.md.
 * Raw SQL như SEED-01: đảm bảo charset/collation/InnoDB/index/FK đúng spec đã verify trên
 * MariaDB 10.11. C-01 nằm ở uq_draws_device_date — DB chặn trùng, không phải code.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE devices (
  device_id   CHAR(26)        NOT NULL,             -- random base32 server sinh, xem 02-db §8
  first_seen  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  session_id  VARCHAR(255)    NULL,                 -- session Laravel gần nhất
  PRIMARY KEY (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        DB::statement(<<<'SQL'
CREATE TABLE draws (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_id      CHAR(26)        NOT NULL,
  user_id        BIGINT UNSIGNED NULL,
  hexagram_id    TINYINT UNSIGNED NOT NULL,
  drawn_date     DATE            NOT NULL,           -- dương lịch Asia/Ho_Chi_Minh, app convert
  lines_rolled   JSON            NOT NULL,           -- 6 hào random server-side, 6|7|8|9 (DƯỚI lên)
  changing_lines JSON            NULL,               -- [vị trí 1-based] hào động (6 hoặc 9), NULL nếu không có
  created_at     TIMESTAMP NULL,
  updated_at     TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_draws_device_date (device_id, drawn_date),  -- C-01 nằm ở ĐÂY, không phải trong code
  KEY idx_draws_device (device_id, created_at),
  CONSTRAINT fk_draws_device FOREIGN KEY (device_id) REFERENCES devices(device_id),
  CONSTRAINT fk_draws_hex FOREIGN KEY (hexagram_id) REFERENCES hexagrams(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS draws');
        DB::statement('DROP TABLE IF EXISTS devices');
    }
};
