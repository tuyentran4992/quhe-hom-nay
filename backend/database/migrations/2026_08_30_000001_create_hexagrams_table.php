<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SEED-01 — bảng `hexagrams` dựng Y NGUYÊN DDL specs/02-db.md §4.
 * Raw SQL thay vì Blueprint: đảm bảo charset/collation/InnoDB và backtick
 * cho reserved word `lines` đúng như spec đã verify trên MariaDB 10.11.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE hexagrams (
  id           TINYINT UNSIGNED NOT NULL,            -- 1..64, thứ tự Văn Vương, KHÔNG auto-inc
  han          VARCHAR(4)       NOT NULL,            -- Hán tự tên quẻ (1–2 ký tự)
  ten          VARCHAR(40)      NOT NULL,            -- "Càn Vi Thiên"
  quoc_am      VARCHAR(20)      NOT NULL,
  upper        VARCHAR(10)      NOT NULL,            -- quái Thượng (vocab đúng 8 quái)
  lower        VARCHAR(10)      NOT NULL,            -- quái Hạ
  `lines`      JSON             NOT NULL,            -- [0|1 x6] từ DƯỚI lên TRÊN. `lines` reserved word — backtick bắt buộc
  symbol       VARCHAR(4)       NOT NULL,            -- U+4DC0..U+4DFF
  dai_ci       TEXT             NOT NULL,            -- 大意
  free_content JSON             NOT NULL,            -- {congViec,tinhDuyen,taiLoc}, mỗi slot ≤45 từ (CUT-45)
  keywords     JSON             NOT NULL,            -- đúng 4 phần tử
  vv_nien      VARCHAR(200)     NOT NULL,            -- Vận niên
  cat          JSON             NOT NULL,            -- ["tot"|"xau"|"trung"]
  ban_goc      JSON             NOT NULL,            -- {quaTu,thoanTruyen,tuongTruyen,haoTu[,dungHao — chỉ id 1,2]}
  luan_nay     TEXT             NOT NULL,            -- luận ngày
  created_at   TIMESTAMP NULL,
  updated_at   TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hexagrams_ten (ten)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS hexagrams');
    }
};
