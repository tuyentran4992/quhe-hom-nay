<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BE-2 — specs/1.mvp/02-db.md §7 `ai_jobs` (DDL nguyên trạng).
 * Queue DATABASE theo 01 §2; trạng thái 1 chiều queued→running→done|failed.
 * requested_at = nguồn đếm cooldown C-03 (idx_ai_jobs_device_time) + cap C-06 (idx_ai_jobs_status).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE ai_jobs (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_uuid      CHAR(36)        NOT NULL,
  device_id     CHAR(26)        NOT NULL,
  draw_id       BIGINT UNSIGNED NOT NULL,
  topic         ENUM('duyen','tai_loc','xuat_hanh') NOT NULL,
  status        ENUM('queued','running','done','failed') NOT NULL DEFAULT 'queued',
  attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  result        MEDIUMTEXT      NULL,
  error_code    VARCHAR(32)     NULL,
  requested_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at   TIMESTAMP       NULL,
  -- DEVIATION BE-2 (03-api #5 F6): 2 cột phục vụ idempotency — không đổi 14 cột spec.
  idempotency_key VARCHAR(64)   NULL,               -- key client sinh, scope theo device
  result_key_hash CHAR(64)      NULL,               -- sha256(draw_id|topic) để so same body
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ai_jobs_uuid (job_uuid),
  KEY idx_ai_jobs_device_time (device_id, requested_at),
  KEY idx_ai_jobs_status (status, requested_at),
  UNIQUE KEY uq_ai_jobs_idem (device_id, idempotency_key),
  CONSTRAINT fk_ai_jobs_device FOREIGN KEY (device_id) REFERENCES devices(device_id),
  CONSTRAINT fk_ai_jobs_draw FOREIGN KEY (draw_id) REFERENCES draws(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS ai_jobs');
    }
};
