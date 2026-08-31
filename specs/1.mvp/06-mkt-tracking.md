# 06 · MKT-F2 — Landing + tracking UTM (contract chính thức)

Card: t_51be9ac4. Base: main @ c4ebcbf. Phạm vi HARD: CHỈ landing + tracking.
Cấm đụng nghi thức gieo quẻ / luận / paywall (rule "không chen lang mạch").

## 1. Kiến trúc (quyết định của dev-lead — 2 bên KHÔNG tự đổi)

- Landing = Blade server-render tại `GET /` (route `backend/routes/web.php`,
  view `backend/resources/views/landing.blade.php`). Lý do: crawl được, không
  cần build, asset dùng inline/CSS trong blade. FE-DEV sở hữu 2 file này.
- App SPA giữ nguyên tại `/app/`. Landing có đúng 1 CTA chính → `/app/`
  và 1 điểm đặt link OA/Zalo (khuôn kênh C): `href` đọc từ env
  `LANDING_OA_URL` (fallback `#` khi trống).
- UTM capture 2 tầng, nguồn sự thật = SERVER (GA4 chỉ phụ):
  1. Landing JS inline parse `location.search` → POST `/api/track` (visit,
     kèm utm) → cùng origin nên cookie `qhn_device` được Set-Cookie luôn
     (EnsureDeviceSession). CTA append `utm_*` đã nhận vào `/app/?...`
     (pass-through, không đổi tên).
  2. SPA: KHÔNG sửa. Attribution nằm server-side theo device.
- GA4: snippet gtag chỉ render khi env `GA4_MEASUREMENT_ID` khác rỗng.
  Tên event GA4 đồng nhất server: `landing_visit`, `cta_gieo_que`.

## 2. DB — 1 migration duy nhất (BE-DEV sở hữu)

`2026_08_31_000007_add_utm_to_devices_and_create_events.php`:

```sql
ALTER TABLE devices
  ADD COLUMN utm_source   VARCHAR(100) NULL AFTER session_id,
  ADD COLUMN utm_medium   VARCHAR(100) NULL,
  ADD COLUMN utm_campaign VARCHAR(100) NULL;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Attribution rule (bất biến, đặt ở 1 nơi — `TrackService`): **first-touch** —
chỉ ghi `devices.utm_*` khi cột đang NULL; sự kiện sau không đè. Cắt gọn
`utm_*` ở 100 ký tự, chỉ `[A-Za-z0-9_\-.,:()/ ]` (khử injection).

`events.name` whitelist: `landing_visit` | `cta_gieo_que` | `donate_open`. Ngoài whitelist → 422.

**MKT-F6-fix (t_a6fbf162, dev-lead 31/08)** — bịt 2 lỗ hổng audit KPI-W1:
1. `cta_gieo_que` trước đây CHỈ bắn qua gtag → GA4 trống thì onclick là no-op,
   không bao giờ vào DB server. Nay landing phải POST `/api/track`
   `{name:'cta_gieo_que', utm}` khi bấm CTA (PA1 — server vẫn là nguồn sự thật,
   GA4 chỉ phụ; 2 nguồn không cộng dồn vào nhau).
2. `donate_open`: SPA POST khi MỞ màn `/mo-khoa/:topic` (PaywallView mounted),
   `props:{topic}`, utm passthrough nếu có. Signal đầu phễu donate.

**Quy ước đọc số của dev-lead (LOCKED)**: CVR GD1 = **draws_devices /
landing_visit_devices theo campaign** (device-level, first-touch attribution —
§6). Cột `clicks` (cta_gieo_que) là tín hiệu chẩn đoán phụ — phát hiện nghẽn
CTA, KHÔNG phải mẫu số/tử số CVR. Lý do chọn device-level: cookie `qhn_device`
có sẵn từ visit, chống bot đơn giản hơn event-count, và không phụ thuộc wiring
onclick vừa hỏng. PA1 vẫn làm để có clicks thật + GA4 đồng nhất tên.

## 3. CONTRACT API (khóa — cả 2 bên code theo đây)

### #11 POST /api/track
- Middleware: `EnsureDeviceSession` (device tự cấp qua cookie). Throttle 30/phút/IP.
- Request `application/json`:
```json
{ "name": "landing_visit",
  "utm": { "source": "fb_group", "medium": "social", "campaign": "w1_que_tinh" },
  "props": { "path": "/", "referrer": "https://facebook.com/..." } }
```
- Payload dạng khác (cùng contract, chỉ đổi `name`/`props`):
  - CTA landing: `{ "name": "cta_gieo_que", "utm": {…passthrough như visit} }` (props optional).
  - Mở paywall:  `{ "name": "donate_open", "utm": {…passthrough nếu có}, "props": { "topic": "duyen" } }`.
- Validate: `name` bắt buộc, whitelist §2. `utm.*` optional string ≤100.
  `props` optional object ≤ 2KB (serialize lại nếu to).
- Response: `204 No Content` (kể cả utm thiếu — vẫn ghi event). Lỗi:
  `422 { "error": { "code": "VALIDATION_FAILED", ... } }` envelope §0.3.
- Hệ quả: insert 1 row `events`; upsert first-touch `devices.utm_*`.
- Idempotency: KHÔNG bắt buộc (double-count visit chấp nhận được ở W1;
  dedupe theo device+name+phút tính sau, không vào contract).

Không endpoint nào khác. Không đổi #1..#10 hiện hữu.

## 4. Landing nội dung (FE-DEV, theo token 04-ui)

- 1 màn, desktop+mobile, không ảnh nặng: headline "Hôm nay bạn là quẻ gì?",
  sub nêu LUẬN SÂU MIỄN PHÍ (luật anh Tuyền 31/08 — không nhắc 29k),
  CTA `data-testid="landing-cta-draw"` → `/app/?<utm pass-through>`,
  khối link OA `data-testid="landing-link-oa"` (env, §1).
- Footer disclaimer "sản phẩm giải trí, tham khảo văn hoá" — đúng từng chữ
  như spec 04-ui.
- JS inline < 2KB: capture + track + GA4 push. Testid đủ cho QA.
- SEO: `<title>Quẻ Hôm Nay — gieo quẻ Kinh Dịch miễn phí</title>`, meta desc,
  `robots` cho index.

## 5. Tiêu chí test (QA-engineer)

1. `curl -c /tmp/j -X POST /api/track -d '{"name":"landing_visit","utm":{"campaign":"test",...}}'` → 204;
   row `events` + `devices.utm_campaign='test'` tra theo device_id trong cookie `/tmp/j`.
2. First-touch: gọi lần 2 campaign khác → cột devices KHÔNG đổi; events có 2 row.
3. `name` rác → 422 envelope §0.3.
4. Landing `/` 200, có CTA href chứa utm_campaign khi vào `/?utm_campaign=test`,
   có/không có gtag đúng theo env.
5. CVR đọc được: SQL mẫu §6 chạy ra số theo campaign.
6. UT BE: TrackApiTest ≥ 5 case; không test nào cần network ngoài.

## 6. Truy vấn KPI (growth-lead đọc số — bản chính thức)

CVR GD1 (quy ước LOCKED §2): `draws_devices / landing_visit_devices` theo
campaign first-touch. `clicks` + `donate_opens` là cột chẩn đoán, không vào CVR.

```sql
-- CVR theo campaign: visit device → draw device (device-level, không đếm event)
SELECT d.utm_campaign,
       COUNT(DISTINCT CASE WHEN e.name='landing_visit' THEN d.device_id END) AS visits,
       COUNT(DISTINCT CASE WHEN e.name='cta_gieo_que'  THEN d.device_id END) AS clicks,
       COUNT(DISTINCT CASE WHEN e.name='donate_open'   THEN d.device_id END) AS donate_opens,
       COUNT(DISTINCT dr.device_id) AS draws,
       ROUND(COUNT(DISTINCT dr.device_id) * 100.0
             / NULLIF(COUNT(DISTINCT CASE WHEN e.name='landing_visit' THEN d.device_id END), 0), 1) AS cvr_pct
FROM devices d
LEFT JOIN events e ON e.device_id = d.device_id
LEFT JOIN draws  dr ON dr.device_id = d.device_id
WHERE d.utm_campaign IS NOT NULL
GROUP BY d.utm_campaign ORDER BY visits DESC;
```

## 7. Luật phân công file (chống conflict)

- BE-DEV: `backend/database/migrations/...000007...`, `app/Http/Controllers/TrackController.php`,
  `app/Services/TrackService.php`, `app/Models/Event.php`, `routes/api.php` (thêm #11),
  `backend/tests/Feature/Api/TrackApiTest.php`.
- FE-DEV: `backend/routes/web.php`, `backend/resources/views/landing.blade.php`,
  `.env.example` (thêm `LANDING_OA_URL`, `GA4_MEASUREMENT_ID`).
- CẤM: be-dev sửa web.php/blade; fe-dev sửa api.php/migrations/tests BE;
  cả hai cấm đụng DrawService/, domain 3 xu.
