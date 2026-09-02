# 05 · TEST PLAN — ca test U/F/E + lệnh preview

Quy ước: U = unit (php artisan test / vitest), F = feature-API (php artisan test --filter
Feature, gọi qua `php artisan serve` hoặc Testing\Http\TestCase), E = E2E browser —
ĐỘC QUYỀN qa-engineer, dev không chạy hộ. Mỗi ca: **bước → kỳ vọng**. Muốn PASS hết để
merge card; số ca = tên cố định, không đánh lại số.

## 0. Lệnh preview (copy-paste chạy được, BE-0 xong là chạy được)

```bash
# Backend (Laravel 11, PHP 8.3, DB quhe_hom_nay đã tồn tại rỗng)
cd /data/quhe-hom-nay/backend
composer install
cp .env.example .env            # điền DB_* local; AIBOX/PAYOS để trống — stub pass
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --port=8000   # API tại http://127.0.0.1:8000/api

# Frontend (một terminal khác)
cd /data/quhe-hom-nay/frontend
npm ci
NODE_OPTIONS=--max-old-space-size=1024 npm run build   # ra backend/public/app/
NODE_OPTIONS=--max-old-space-size=1024 npm run dev     # hoặc dev server FE 5173 proxy /api→8000
```

```bash
# QA nhanh không cần browser (curl) — chuỗi sống của MVP:
curl -sc /tmp/q.jar http://127.0.0.1:8000/api/me                      # lấy cookie device
curl -sb /tmp/q.jar -X POST http://127.0.0.1:8000/api/draws -H 'Content-Type: application/json' -d '{}'
curl -sb /tmp/q.jar -X POST http://127.0.0.1:8000/api/ai/interpretations \
  -H 'Content-Type: application/json' \
  -d '{"draw_id":1,"topic":"duyen","idempotency_key":"t-01"}'          # kỳ vọng 402
```

## 1. UNIT (U1–U6)

**U1 — Seeder đủ 64 + idempotent.** Bước: `php artisan migrate:fresh --seed` rồi chạy tiếp
`php artisan db:seed --class=HexagramSeeder` lần 2. Kỳ vọng: `SELECT COUNT(*)=64`,
`SELECT SUM(id)=2080`, row id=1 `ten='Càn Vi Thiên'`, total row vẫn 64 (không duplicate).

**U2 — Seeder strip canSoi.** Bước: `SELECT COUNT(*) FROM hexagrams WHERE JSON_SEARCH(ban_goc,'one','canSoi',NULL,'$.*') IS NOT NULL OR JSON_SEARCH(free_content,'one','canSoi',NULL,'$.*') IS NOT NULL` (và tương tự cột khác). Kỳ vọng: 0; grep file JSON nguồn commit trong repo = 0 lần `canSoi`.

**U3 — CUT-45.** Bước: test PHP duyệt 64 row: mỗi slot `free_content.{congViec,tinhDuyen,taiLoc}` split whitespace. Kỳ vọng: mọi slot ≤45 từ (0/192 vi phạm — đã kiểm offline khi viết spec).

**U4 — RngDrawService 3 xu (SPEC-3XU, thay bản cỏ-thi cũ).** Bước: gọi hàm rolling
(03-api §3.1) đủ 6 hào, lặp **≥ 200.000 hào** (240k+ lần `random_int`, chạy trong test PHP
thời gian <60s bằng cách gọi thẳng service, không qua HTTP). Kỳ vọng: (a) mọi giá trị
∈{6,7,8,9}; (b) tần suất 4 loại lệch không quá **5σ** so với 12.5/37.5/37.5/12.5 — với
n=200.000 hào, σ = √(n·p·(1−p)): p=1/8 → σ≈148 (ngưỡng ±741), p=3/8 → σ≈217 (ngưỡng
±1083); (c) `changing_lines` khớp đúng vị trí mang 6/9; (d) 6 hào trả về luôn khớp
1 trong 64 pattern `hexagrams.lines` (không quẻ ảo); (e) grep mã roller: chỉ `random_int`,
không `rand(`/`mt_rand(`.

**U5 — Rules bất biến.** Bước: assert `Domain\Rules` constants: `AI_COOLDOWN_SECONDS===90`
(giây — KHÔNG có constant "90 lần"), `PRICE_UNLOCK_VND===29000`, `FREE_DRAW_PER_DAY===1`,
`AI_GLOBAL_CAP_PER_HOUR===90`, `DONATE_MIN_VND===1000`, `DONATE_MAX_VND===500000`.
Kỳ vọng: pass; thêm test quét source: không magic number 29000/90 nằm ngoài Rules.php
(grep controller/service).

**U6 — Validate amount donate.** Bước: unit request `PaymentRequest` với amount 999 /
1000 / 500000 / 500001 / 29000+1 khi kind=unlock. Kỳ vọng: fail 2 ca đầu-cuối theo C-07;
unlock luôn bị ghi đè 29000 (C-05) dù client gửi gì.

**U7 — HaoTextSeeder đủ 64×6, không rỗng (SPEC-3XU).** Bước: `migrate:fresh --seed` rồi
`php artisan db:seed --class=HaoTextSeeder` lần 2. Kỳ vọng: `SELECT COUNT(*)=384`,
`SELECT COUNT(*) FROM hexagram_hao_texts WHERE han='' OR quoc_am='' OR nghia=''` = 0, mỗi
`hexagram_id` đúng 6 dòng `vi` 1..6 distinct, chạy 2 lần vẫn 384 (idempotent); đối chiếu
ngẫu nhiên 3 hào với file nguồn `hao_texts.json` khớp nguyên văn `han`. Lưu ý từ dev-lead
(đã verify DDL thật 31/08): cột NOT NULL KHÔNG chặn chuỗi rỗng ở MariaDB — chốt "không rỗng"
bằng `<>''` trong test + seeder phải FAIL-to-loud nếu nguồn thiếu bất kỳ field nào.

## 2. FEATURE / API (F1–F9) — đối chiếu 03-api từng field

**F1 — GET /api/me thiết bị lạ.** Bước: curl không cookie. Kỳ vọng: 200, `Set-Cookie:
qhn_device=...HttpOnly`, body đủ 5 field §1, `today_draw=null`, `is_new_device=true`.

**F2 — Vòng gieo 1 quẻ.** Bước: #3 với device mới. Kỳ vọng: 201; `data.hexagram.id` khớp
`data.draw.hexagram_id`; `lines_rolled` 6 phần tử {6..9}; response có draw object đúng
shape §3.2 (đủ 6 field, RFC3339).

**F3 — C-01 chặn ngày.** Bước: gieo lần 2 cùng device cùng `server_date_vn`. Kỳ vọng: 409
`DRAW_LIMIT_REACHED`, envelope §0.3, `details.next_draw_at` = RFC3339 chỉ 0h VN kế tiếp.
(Unit-test hỗ trợ fake date: test phải pass khi service nhận `DateProvider` mock.)

**F4 — 402 gate.** Bước: #5 `topic=duyen` khi chưa paid. Kỳ vọng: 402 đúng payload mẫu
§5 (field `price_vnd:29000`).

**F5 — Cooldown 90 GIÂY.** Bước: device đã paid (dùng #7b simulate-paid): gọi #5 hợp lệ,
gọi tiếp ngay lần 2. Kỳ vọng: 202 rồi 429 `AI_COOLDOWN` với `retry_after_seconds` ≤90;
mock `requested_at` cũ 91 giây → được 202. (Chốt lệch cũ: không có khái niệm "90 lần".)

**F6 — Idempotency #5/#7.** Bước: gửi lại exact body+key → kỳ vọng trả CÙNG job_uuid/
order_code (200/202, không row mới); cùng key khác body → 409 `IDEMPOTENCY_CONFLICT`.

**F7 — Poll job của ai khác.** Bước: device B GET /api/ai/jobs/<uuid của A>. Kỳ vọng: 404
`NOT_FOUND` (không 403 — ẩn tồn tại). Tương tự #9 đơn của A → 404.

**F8 — Webhook stub + trạng thái.** Bước: #7 `kind=unlock,topic=tai_loc` → #7b simulate →
#9. Kỳ vọng: #9 `status=paid`, `paid_at` RFC3339; entitlement device xuất hiện trong
`GET /api/me.entitlements=['tai_loc']`; gọi #7b lần 2 idempotent không lỗi. Webhook thật
#8 ký sai signature → 401.

**F9 — `hao_texts` trong #3 + endpoint #2b (SPEC-3XU).** Bước: mock roller trả
`lines_rolled` có k hào động (test helper, không mong chờ ngẫu nhiên) rồi gọi #3; gọi #2b
id=11 và id=65. Kỳ vọng: #3 201, `data.hao_texts` đúng k phần tử, `vi` tăng dần sơ→thượng,
mỗi phần tử đủ 5 field `{vi,hao,han,quoc_am,nghia}`; k=0 → `hao_texts: []` (không null);
#2b id=11 → 200 đúng 6 phần tử; id=65 → 404.

## 3. E2E / UI-GATE (E1–E7) — qa-engineer chạy trên bản build (preview §0)

**E1 — First-run flow.** Bước: incognito mở `/` → Gieo → xem 3 ngôi → về `/`. Kỳ vọng:
không console error; disclaimer hiện trên mọi màn; quẻ hiện khớp symbol/ten.

**E2 — Magic sequence 3 xu ≥1.5s (SPEC-3XU).** Bước: record frame (Playwright) từ chạm đến
reveal. Kỳ vọng: ≥1.500 ms, đủ 6 hào theo thứ tự dưới→trên, mỗi bước gieo thể hiện 3 xu
(count số phần tử xu trong DOM tại frame giữa animation — shape chờ UX-3XU chốt, test chỉ
bắt buộc ≥3 xu mỗi hào); request #3 bay song song không chờ animation.

**E3 — Paywall stub.** Bước: S3 → Xin luận sâu (duyên) → Paywall → QR hiển thị, input
"Lễ tùy tâm" chỉ nhận 1.000–500.000 → simulate-paid (test local dùng #7b) → luận sâu poll
ra văn bản. Kỳ vọng: luồng không tải lại trang, nút disabled khi pending.

**E4 — Từ cấm.** Bước: crawl 5 route (DOM text) grep §05-tu-cấm: "hóa giải|cúng|giải hạn|
bùa|thay đổi vận mệnh|tâm linh|thỉnh|cốt". Kỳ vọng: 0 kết quả (kể cả alt/aria).

**E5 — Cooldown UX.** Bước: xin 1 luận sâu, bấm ngay lần 2. Kỳ vọng: đếm ngược ≤90s đúng
`retry_after_seconds` server trả, nút disabled, hết thời gian bấm được.

**E6 — Session không mất tiền.** Bước: paid topic duyen → xóa cookie `laravel_session`
(giữ `qhn_device` — mock bằng cách đổi session id trên server) → /api/me. Kỳ vọng:
`entitlements` còn `duyen`, S3 mở luận sâu được luôn.

**E7 — Luận 0 / 1 / nhiều hào động (SPEC-3XU).** Bước: dùng test-mode mock roller trên
preview (env `QA_MOCK_LINES` — BE-1 chừa: đọc JSON 6 số từ env, chỉ bật khi APP_ENV!=production)
lần lượt gieo 3 ca: không hào động / 1 hào động / 3 hào động. Kỳ vọng: S3 hiển thị đúng 1
khối đại ý (k=0); đại ý + 1 khối từ hào (k=1); đại ý + 3 khối từ hào theo thứ tự sơ→thượng,
mỗi khối đủ Hán + quốc âm + nghĩa (k=3); cả 3 ca KHÔNG có tên/symbol quẻ biến trên màn.

## 4. Definition of Done toàn chain

- [ ] U1–U7 + F1–F9 xanh trên CI local (`php artisan test`) trước mỗi merge card BE.
- [ ] E1–E7 xanh do qa-engineer xác nhận (dev-lead nhận report + screenshot, không tự chạy).
- [ ] 03-api field-by-field khớp FE network panel (QA diff JSON mẫu vs thực tế 3 ca F2/F4/F8).
- [ ] Không file >250 dòng; không magic number ngoài `config/project.php` (số/CỜ) + `Domain/Rules` (enum cấu trúc); không secret trong git
      (`git grep -Ii 'AIBOX_API_KEY=.\+' ; git grep -i 'password'.env.example` → chỉ placeholder).
