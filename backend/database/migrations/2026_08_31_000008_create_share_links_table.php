<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F7-BE — SPEC-THE §5 / F7-CONTRACT §2: 1 migration DUY NHẤT tạo bảng share_links.
 * KHÔNG sửa draws/devices. UNIQUE(draw_id, device_id) phục vụ idempotency
 * same device+draw → same token (không cần SELECT-then-INSERT race-safe).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE share_links (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  token      CHAR(10)        NOT NULL,
  draw_id    BIGINT UNSIGNED NOT NULL,
  device_id  CHAR(26)        NOT NULL,
  created_at TIMESTAMP       NULL DEFAULT NULL,
  views      INT             NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_share_links_token (token),
  UNIQUE KEY uq_share_links_draw_device (draw_id, device_id),
  KEY idx_share_links_device (device_id),
  CONSTRAINT fk_share_links_draw FOREIGN KEY (draw_id) REFERENCES draws(id),
  CONSTRAINT fk_share_links_device FOREIGN KEY (device_id) REFERENCES devices(device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS share_links');
    }
};
