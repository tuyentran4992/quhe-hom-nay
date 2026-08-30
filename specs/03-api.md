# 03 · API — CONTRACT (LUẬT: FE/BE code khớp TỪNG field)

Base URL: `/api` · JSON-only · Content-Type `application/json` · charset utf-8.
Auth: cookie-first — `qhn_device` (HttpOnly, xem 02-db §8) + `laravel_session`.
KHÔNG có API công khai nào cần Bearer trong MVP. Mọi response lỗi dùng 1 envelope §0.3.

## 0. Hằng số nghiệp vụ — nguồn duy nhất `backend/app/Domain/Rules.php`

| ID | Tên code | Giá trị CHỐT | Ý nghĩa |
|---|---|---|---|
| C-01 | `FREE_DRAW_PER_DAY` | `1` | 1 quẻ free / device / ngày dương lịch VN ( enforce bằng `uq_draws_device_date`) |
| C-02 | `TOPICS` | `duyen, tai_loc, xuat_hanh` | 3 chủ đề unlock (enum DB, đúng 3 giá trị) |
| C-03 | `AI_COOLDOWN_SECONDS` | **`90`** | cooldown GIỮA 2 lần xin luận sâu của 1 device = **90 giây thời gian**. LỆCH BẢN CŨ CHỐT Ở ĐÂY: bản cũ 01-overview ghi "cap 90s", 03-api ghi "cap 90 lần ~225s" → thống nhất: **90 giây**, không tồn tại "90 lần". |
| C-04 | `AI_TIMEOUT_SECONDS` / `AI_MAX_ATTEMPTS` | `120` / `3` | 1 job AI chết sau 120s, tối đa 3 lần thử |
| C-05 | `PRICE_UNLOCK_VND` | `29000` | giá one-time / chủ đề, đơn vị đồng (VND chẵn, không có 29k lẻ) |
| C-06 | `AI_GLOBAL_CAP_PER_HOUR` | `90` | cap TOÀN CỤC job AI tạo mới trong 60 phút gần nhất (đếm `ai_jobs.requested_at`) |
| C-07 | `DONATE_MIN_VND` / `DONATE_MAX_VND` | `1000` / `500000` | khoảng tiền "Lễ tùy tâm" |
| C-08 | `MAGIC_SEQUENCE_MS` | `1500` | FE tối thiểu cho animation gieo quẻ (04-ui); BE không enforce |

Quy ước chung: sai validate → 422 + `errors[]` theo field; vi phạm rule C-xx → đúng mã
4xx ghi cạnh rule; mọi timestamp RFC3339 UTC; mọi `amount` là INTEGER đồng (không float).

### 0.3 Error envelope (MỌI endpoint lỗi trả đúng hình này)

```json
{ "error": { "code": "DRAW_LIMIT_REACHED", "message": "Hôm nay bạn đã gieo quẻ rồi. Quay lại sau 0h.", "details": { "next_draw_at": "2026-08-31T17:00:00Z" } } }
```

Bảng mã lỗi toàn cục (HTTP + `error.code`):

| HTTP | code | Khi nào | Endpoint |
|---|---|---|---|
| 400 | `BAD_REQUEST` | JSON malformed, thiếu field bắt buộc | all |
| 401 | `UNAUTHENTICATED` | webhook token sai / API key lạ gọi vào | #8 |
| 402 | `UNLOCK_REQUIRED` | xin luận sâu khi chưa trả phí | #5 |
| 404 | `NOT_FOUND` | id/uuid không tồn tại hoặc không phải của device này | #4 #6 #9 |
| 409 | `IDEMPOTENCY_CONFLICT` | cùng `idempotency_key` payload khác | #5 #7 |
| 409 | `DRAW_LIMIT_REACHED` | vi phạm C-01 | #3 |
| 422 | `VALIDATION_FAILED` | validate fail (`details.errors` liệt kê field) | all |
| 429 | `AI_COOLDOWN` | vi phạm C-03 (kèm `details.retry_after_seconds`) | #5 |
| 429 | `AI_GLOBAL_CAP` | vi phạm C-06 | #5 |
| 500 | `INTERNAL` | crash; không lộ stack | all |
| 503 | `AI_BUSY` | worker chết quá `AI_TIMEOUT_SECONDS×3` — client nên thử lại sau | #6 (job failed) |

---

## 1. `GET /api/me` — bootstrap phiên device

Không request param. Server đảm bảo cookie `qhn_device` (mới thì sinh + Set-Cookie).

Response 200:

| field | type | note |
|---|---|---|
| `device_id` | string(26) | chỉ để debug; FE không cần dùng |
| `is_new_device` | bool | true khi vừa sinh device |
| `today_draw` | object\|null | draw hôm nay (null nếu chưa gieo) — cấu trúc §3.2 `draw` |
| `entitlements` | string[] | topic đã unlock, phần tử ⊆ C-02, vd `["duyen"]` |
| `server_date_vn` | string(date) | `YYYY-MM-DD` theo Asia/Ho_Chi_Minh — FE MŨI TÊN đồng bộ "ngày" |

```json
{"device_id":"7f3kq2m5t9vxz1b4c6d8e0g2i4","is_new_device":false,"today_draw":null,"entitlements":[],"server_date_vn":"2026-08-30"}
```

Lỗi: không có (200 ngay cả device lạ — self-healing).

## 2. `GET /api/hexagrams/{id}` — tra cứu quẻ

Path: `id` int 1..64. Query `?fields=` bỏ qua (chấp nhận, không dùng) — trả full.

Response 200 — `data` (ánh xạ 1-1 cột bảng `hexagrams`, snake_case nguyên DB):
`id, han, ten, quoc_am, upper, lower, lines(int[6]), symbol, dai_ci, free_content{congViec,tinhDuyen,taiLoc}, keywords(string[4]), vv_nien, cat(string[]), ban_goc{quaTu,thoanTruyen,tuongTruyen,haoTu[],dungHao?}, luan_nay`.
KHÔNG BAO GIỜ có `canSoi` (không tồn tại trong DB).

```json
{"data":{"id":1,"han":"乾","ten":"Càn Vi Thiên","quoc_am":"Càn","upper":"Càn","lower":"Càn","lines":[1,1,1,1,1,1],"symbol":"䷀","dai_ci":"Sáu hào đều dương — trời chạy mãi không nghỉ.","free_content":{"congViec":"...","tinhDuyen":"...","taiLoc":"..."},"keywords":["càn","tự cường","dẫn đầu","hành động"],"vv_nien":"Năm của người đứng mũi chịu sào...","cat":["trung"],"ban_goc":{"quaTu":{"han":"乾：元，亨，利，貞。","am":"Kiền: nguyên, hanh, lợi, trinh.","nghia":"..."},"thoanTruyen":{},"tuongTruyen":{},"haoTu":[{"vi":1,"hao":"Sơ Cửu","han":"...","am":"...","nghia":"..."}],"dungHao":{"han":"用九：見羣龍無首，吉。","am":"Dụng cửu...","nghia":"..."}},"luan_nay":"..."}}
```

Lỗi: 404 `NOT_FOUND` (id ngoài 1..64 hoặc chưa seed).

## 3. `POST /api/draws` — gieo quẻ hôm nay (C-01)

Request body: `{}` (rỗng cũng hợp lệ; trường `client_date_vn` string\|optional CHỈ để log
đối chiếu lệch ngày, server KHÔNG dùng tính toán).

Logic server (BẤT BIẾN, BE-1 code đúng như này — mô phỏng gieo cỏ thi đơn giản hóa):
mỗi hào (6 hào, dưới→trên): `r = random_int(1,100)`; r≤44 → 7 (dương tĩnh); r≤88 → 8 (âm
tĩnh); r≤94 → 9 (dương động); else → 6 (âm động). `changing_lines` = vị trí 1-based mang
giá trị 6 hoặc 9. Quẻ gốc: hào dương (7|9)=1, âm (6|8)=0 → bitmask 6 bit dưới→trên, tra
`hexagrams.lines` khớp 1-1 (64 pattern unique, đã verify). Nếu có hào động, luận vẫn trả
theo quẻ gốc MVP (quẻ biến = nội dung sóng 2, chưa mở endpoint). Ghi `draws` (unique
C-01 chặn trùng), trả 201.

Response 201 — `data`:

| field | type | note |
|---|---|---|
| `data.draw` | draw §3.2 | id hexagram_id drawn_date lines_rolled changing_lines created_at |
| `data.hexagram` | object | như §2 `data` (đủ 16 field, đã kiểm schema) |
| `data.already_drawn` | bool | luôn false ở 201 |

### 3.2 `draw` object (dùng chung ở #1 #3 #10)

`{ "id": int, "hexagram_id": int, "drawn_date": "YYYY-MM-DD", "lines_rolled": int[6] (mỗi pt ∈ {6,7,8,9}), "changing_lines": int[] (1-based ⊆1..6), "created_at": RFC3339 }`

```json
{"data":{"draw":{"id":42,"hexagram_id":11,"drawn_date":"2026-08-30","lines_rolled":[7,8,7,7,7,7],"changing_lines":[2],"created_at":"2026-08-30T02:15:00Z"},"hexagram":{"id":11,"han":"泰","ten":"Địa Thiên Thái","...":"..."},"already_drawn":false}}
```

Lỗi: 409 `DRAW_LIMIT_REACHED` (C-01, `details.next_draw_at` = 0h VN kế tiếp). Không có lỗi
500 hợp lệ khác — random phía server.

## 4. `GET /api/draws/history?limit=` — sổ cá nhân (màn Library)

Query: `limit` int 1..50 default 20. Response 200: `{ "data": draw[] §3.2, "meta": { "count": int } }` (mới nhất trước). Không phân trang trong MVP.
Lỗi: 422 `VALIDATION_FAILED` (limit ngoài khoảng).

## 5. `POST /api/ai/interpretations` — xin luận sâu (gate 402 + C-03 + C-06)

Request body — tất cả bắt buộc:

| field | type | rule |
|---|---|---|
| `draw_id` | int | phải là draw của device này, thuộc `server_date_vn` hôm nay hoặc hôm qua |
| `topic` | string | ⊆ C-02 (`duyen`/`tai_loc`/`xuat_hanh`) |
| `idempotency_key` | string(8-64) | client sinh (uuid); same key+same body = same job 200 |

Logic thứ tự: (a) validate → 422; (b) entitlement (§6 payments paid cho topic) → 402
`UNLOCK_REQUIRED`; (c) cooldown 90 GIÂY (C-03) theo `ai_jobs.requested_at` của device →
429 `AI_COOLDOWN` + `details.retry_after_seconds`; (d) cap toàn cục 90 job/60 phút (C-06) →
429 `AI_GLOBAL_CAP`; (e) INSERT `ai_jobs` status=queued + dispatch job queue → 202.

Response 202 — `{ "data": { "job_uuid": char36, "status": "queued", "topic": "...", "draw_id": int, "poll_url": "/api/ai/jobs/<uuid>" } }`

```json
{"data":{"job_uuid":"9f0d3c2e-1a2b-4c5d-8e9f-0a1b2c3d4e5f","status":"queued","topic":"duyen","draw_id":42,"poll_url":"/api/ai/jobs/9f0d3c2e-1a2b-4c5d-8e9f-0a1b2c3d4e5f"}}
```

Payload lỗi mẫu 402: `{"error":{"code":"UNLOCK_REQUIRED","message":"Chủ đề này cần mở khóa 29.000đ.","details":{"topic":"duyen","price_vnd":29000,"payment_create_url":"/api/payments/create"}}}`
Payload lỗi mẫu 429 cooldown: `{"error":{"code":"AI_COOLDOWN","message":"Bạn vừa xin luận giải, nghỉ tay 90 giây đã.","details":{"retry_after_seconds":57}}}`

## 6. `GET /api/ai/jobs/{job_uuid}` — poll kết quả

Response 200 — `{ "data": { "job_uuid": str, "status": "queued|running|done|failed", "topic": str, "result": string|null (markdown, ~200-400 từ), "error_code": string|null, "requested_at": RFC3339, "finished_at": RFC3339|null } }`

```json
{"data":{"job_uuid":"9f0d3c2e-...","status":"done","topic":"duyen","result":"Duyên hôm nay đọc từ hào động hai của quẻ Thái...","error_code":null,"requested_at":"2026-08-30T02:20:00Z","finished_at":"2026-08-30T02:20:23Z"}}
```

Client poll 2 giây/lần, dừng khi done/failed, tối đa 130 giây rồi hiện thử-lại. Lỗi:
404 `NOT_FOUND` (uuid lạ — KHÔNG được lộ sự tồn tại của device khác). Job `failed` vẫn 200
kèm `error_code` ∈ {AI_TIMEOUT, AI_UPSTREAM, AI_FILTERED}.

## 7. `POST /api/payments/create` — tạo đơn (STUB cho PAY-01 — contract ĐÃ chốt, code thật sóng 2)

Request:

| field | type | rule |
|---|---|---|
| `kind` | `"unlock"\|"donate"` | bắt buộc |
| `topic` | string | C-02 — bắt buộc khi unlock, PHẢI vắng khi donate (422) |
| `amount_vnd` | int | unlock: server TỰ set 29000 (client gửi khác → ghi đè 29000, không lỗi); donate: 1000..500000 (C-07) |
| `return_url` | string | FE trang cảm ơn; whitelist origin |
| `idempotency_key` | string(8-64) | bắt buộc; trùng key+trùng body → trả lại đơn cũ 200 (không tạo mới) |

Hành vi PAY-01 chưa deploy: BE tạo row `payments.status=pending`, `order_code` thật, và trả
qr `data.stub=true` + `confirm_url` trỏ endpoint giả lập #7b. **Contract response GIỐNG HẸT
bản payOS thật để FE không phải sửa gì khi PAY-01 merge.**

Response 201:

```json
{"data":{"order_code":17880833415,"kind":"unlock","topic":"duyen","amount_vnd":29000,"status":"pending","qr_data":"vietqr/action/qr/970436/0123456789/2900000/Ngay+cho+Qu+Hom+Nay","confirm_url":"https://demo-pay.example.test/pay/17880833415","checkout_url":"/pay/17880833415","stub":true,"expires_at":"2026-08-30T04:00:00Z"}}
```

Lỗi: 409 `IDEMPOTENCY_CONFLICT` (same key, khác body); 422 `VALIDATION_FAILED`.

## 7b. `POST /api/payments/{order_code}/simulate-paid` — CHỈ env local/qa

Đánh thức webhook cho FE test: set paid + bắn đúng handler #8 nội bộ. 404 nếu `APP_ENV=production`.

## 8. `POST /api/webhooks/payos` — IPN thật (PAY-01; spec chốt trước để BE-0预留 đường)

Headers: `X-PayOS-Signature` (HMAC SHA256 hex của raw body với `PAYOS_WEBHOOK_SECRET`).
Body payOS: `{ "data": { "code", "id", "orderCode", "amount", "cancelled", "payDate", "transactionRef", ... } }` — BE chỉ tin 3 field: `orderCode, amount, transactionRef`.
Xử lý: verify signature → 401 nếu sai; tìm payment theo `order_code`; idempotent theo
`(gateway_ref)`: webhook lặp trả 200 ngay. `paid` khi `!cancelled && amount == payments.amount_vnd`.
Response: `{"error":{"code":"OK"}}` — **đúng format payOS yêu cầu** (họ check chuỗi này).
Sai số tiền → `status='expired'`, log cảnh báo, vẫn 200.

## 9. `GET /api/payments/{order_code}/status` — FE poll sau khi khách rời trang QR

Response 200: `{ "data": { "order_code": int, "status": "pending|paid|cancelled|expired|refunded", "kind": str, "topic": str|null, "amount_vnd": int, "paid_at": RFC3339|null } }`
Lỗi 404 `NOT_FOUND` (đơn của device khác — ẩn tồn tại như §6).

## 10. `GET /api/me/today` — alias đọc nhanh cho FE (không gieo)

Trả `{ "data": { "today_draw": draw|null §3.2, "entitlements": str[], "server_date_vn": "YYYY-MM-DD" } }` —
chính là 3 field cuối của §1 (implement bằng cùng Service). Có để FE khỏi tải `hexagram`
mỗi lần refetch. 200 không điều kiện.

---

## 11. Ma trận field FE↔BE (tóm tắt chống lệch — QA-0 đối chiếu bằng test)

| Màn (04-ui) | Endpoint | FE đọc field |
|---|---|---|
| Home | #1 | `today_draw, entitlements, server_date_vn` |
| Draw | #3 | `data.draw, data.hexagram.free_content` |
| Detail 3 ngôi | #3 cache + #2 | `free_content.{congViec,tinhDuyen,taiLoc}`, `changing_lines` |
| Paywall | #7 → #9 | `qr_data, confirm_url` → poll `status` |
| Deep AI | #5 → #6 | `job_uuid` → `result` |
| Library | #4 | `data[]` |

## 12. Việc PAY-01 được CHỪA sẵn (không phải nợ của BE-0..2)

1. Thay stub §7 = gọi payOS `POST /payments` thật + verify checksum §8 bằng key boss cấp.
2. Không đổi TÊN/SHAPE bất kỳ field nào ở §7/§8/§9; `stub:true` biến mất là khác biệt duy nhất.
3. Card PAY-01 chỉ được sửa 03-api.md qua dev-lead (README: "API sửa = sửa 03-api trước").
