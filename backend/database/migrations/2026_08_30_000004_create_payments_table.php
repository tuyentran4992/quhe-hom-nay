<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BE-2 — specs/1.mvp/02-db.md §6 `payments` (DDL nguyên trạng).
 * entitlement = (device_id, kind='unlock', topic, status='paid') — uq_payments_entitlement
 * chặn mua trùng; donate topic NULL không bị UNIQUE chặn lặp (UNIQUE bỏ qua NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE payments (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_code      BIGINT UNSIGNED NOT NULL,
  device_id       CHAR(26)        NOT NULL,
  user_id         BIGINT UNSIGNED NULL,
  kind            ENUM('unlock','donate') NOT NULL DEFAULT 'unlock',
  topic           ENUM('duyen','tai_loc','xuat_hanh') NULL,
  amount_vnd      INT UNSIGNED    NOT NULL,
  status          ENUM('pending','paid','cancelled','expired','refunded') NOT NULL DEFAULT 'pending',
  gateway_ref     VARCHAR(64)     NULL,
  paid_at         TIMESTAMP NULL,
  idempotency_key VARCHAR(64)     NOT NULL,
  request_hash    CHAR(64)        NULL,  -- DEVIATION BE-2: sha256 body chuẩn, so same-key-different-body (F6)
  created_at      TIMESTAMP NULL,
  updated_at      TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payments_order (order_code),
  UNIQUE KEY uq_payments_idem (idempotency_key),
  UNIQUE KEY uq_payments_entitlement (device_id, kind, topic),
  KEY idx_payments_status (status, created_at),
  CONSTRAINT fk_payments_device FOREIGN KEY (device_id) REFERENCES devices(device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS payments');
    }
};
