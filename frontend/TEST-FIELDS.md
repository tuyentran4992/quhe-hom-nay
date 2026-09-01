# TEST-FIELDS — FE-0 scaffold + FE-1 API thật · mapping màn hình → data-testid → ý nghĩa

Nguồn: `mockups/s1-home/TESTIDS.md` (v3, boss duyệt a670766) cho S1; specs/1.mvp/04-ui.md §2-§4 cho S2-S5.
Quy ước QA: soi element bằng `[data-testid="..."]`. Cột "Khi nào" = điều kiện render; QA assert cả hai nhánh.

## S1 Home `/` — HomeView.vue
| testid | khi nào | nội dung / hành vi |
|---|---|---|
| `home-logo` | luôn | "Quẻ Hôm Nay", font han |
| `home-seal` | luôn | "今日" trang trí, aria-hidden |
| `home-nav-draw` | desktop md+ | link → `/draw`, chữ "Gieo quẻ" |
| `home-nav-library` | desktop md+ | link → `/cua-ban`, chữ "Sổ quẻ của bạn" |
| `home-loading` | đang gọi #1 | "Đang mở bàn cờ…" (không trắng màn) |
| `home-error` | #1 lỗi | "Không tải được dữ liệu. Kiểm tra mạng rồi thử lại." |
| `home-server-date` | có dữ liệu | `server_date_vn` → `dd/MM/yyyy` — MŨI TÊN: không dùng đồng hồ máy (TESTIDS #4) |
| `home-cta-card` | `today_draw === null` | card "Hôm nay chưa gieo quẻ" |
| `home-cta-draw` | `today_draw === null` | link → `/draw`, "Gieo quẻ hôm nay" |
| `home-today-card` | `today_draw != null` | article tóm tắt quẻ hôm nay |
| `home-hexagram-symbol` | đã gieo | glyph từ #2 (cache `useHexagrams`, tra theo `today_draw.hexagram_id` — shape §3.2 không embed) |
| `home-hexagram-name` | đã gieo | `ten` + `han` → "Địa Thiên Thái 泰" |
| `home-hexagram-pending` | #2 chưa về/lỗi | "…" hoặc dòng lỗi nhẹ — card vẫn hiện, không trắng |
| `home-changing-lines` | `changing_lines.length > 0` | cinnabar "Hào 2 động — quẻ biến: …"; rỗng → KHÔNG render |
| `home-slot-congViec` | đã gieo | `free_content.congViec` (cắt ~2 dòng) |
| `home-link-detail` | đã gieo | NÚT chính → `/que/{today_draw.id}` "Xem đủ ba ngôi + bản gốc →" |
| `home-link-detail-inline` | đã gieo | link phụ "xem thêm" → cùng route |
| `home-topics-title` | luôn | "Chủ đề luận sâu" |
| `home-chip-duyen` / `home-chip-tai-loc` / `home-chip-xuat-hanh` | luôn | link → `/mo-khoa/{topic}` (key API snake: `tai_loc`, `xuat_hanh`) |
| `home-chip-*-icon` | luôn | open → `✓` ; locked → `🔒` (aria-hidden) |
| `home-chip-*-state` | entitlement mở | "đã mở" |
| `home-chip-*-price` | khóa | "29.000đ" (C-05, không đổi) |
| `home-disclaimer` | luôn | DisclaimerBar — wording 04-ui §5 TỪNG CHỮ |

## S2 Gieo quẻ `/draw` — DrawView.vue + MagicSequence.vue
| testid | khi nào | nội dung / hành vi |
|---|---|---|
| `draw-frame` | luôn | khung giữa màn |
| `draw-start` | idle | nút "Tâm tĩnh, chạm để gieo" → bắn #3 SONG SONG animation |
| `magic-sequence` | sau bấm | 6 hào vẽ lần lượt từ DƯỚI lên |
| `data-draw-line="1..6"` | rolling | 1 = hào dưới (đánh dấu mốc animation từng hào) |
| `reveal-hexagram` | T ≥ 1500ms | symbol + ten — BẤT BIẾN C-08: không bao giờ sớm hơn 1.5s |
| `draw-spinner` | API chậm hơn 1.5s | "Đang mở quẻ…" nối mạch, không trắng |
| `draw-result` | T ≥ 1.5s VÀ #3 về | "{ten} — đang vào bảng giải…" → 0.6s sau auto-push S3; #3 về cũng PRIME cache #2 (S3 không fetch #2 lại) |
| `draw-error` + `draw-retry` | #3 lỗi KHÔNG phải 409 (mạng/500) | dòng lỗi + nút "Gieo lại" — bấm retry bắn #3 lần 2, vẫn giữ C-08 |
| `disclaimer-bar` | luôn | App shell |

DRAW_LIMIT_REACHED (409) → replace về `/` + `?toast=draw_limit` → S1 render `toast-stack` / `toast-{n}` nội dung "Hôm nay đã gieo rồi, hẹn 0h."

## S3 Kết quả `/que/:drawId` — DetailView.vue
| testid | khi nào | nội dung |
|---|---|---|
| `detail-loading` / `detail-error` | đang resolve draw/#2 / không tìm thấy draw (deep-link quẻ lạ, id hết hiệu lực) hoặc lỗi mạng | trạng thái, không trắng màn; `detail-error` có link "Về trang chính". FE-1: draw hôm nay → từ #1; quẻ quá khứ → resolve qua #4 (limit 50) rồi #2 — contract KHÔNG có GET /draws/{id} |
| `detail-linechart` | có dữ liệu | LineChart 6 hào; `data-line="0\|1"` trên→dưới = hào 6→1; `data-position="1..6"`; hào động (6/9) thêm chấm `dot` + chữ "động" + **outline cinnabar quanh `.ln-bar`** (fix t_c09526c3). Cấu trúc mới: mỗi row `[data-line]` = `.ln-bar` (width definite `w-16/24/40` theo size, chứa `.ln-seg` 1 dương/2 âm) + `.ln-aside` (`w-20`, chứa dot+nhãn). QA đo: `bar.getBoundingClientRect().width` phải = 64/96/160px, seg âm mỗi cái ≈ (bar−gap)/2 > 0 |
| `detail-hexagram-name` | có dữ liệu | `ten` + `han` + symbol — [UI-POLISH t_fc6387df] cỡ DISPLAY `text-h1` font serif `.han`, nổi nhất header |
| `detail-chip-index` | luôn | [UI-POLISH t_fc6387df] chip "Chỉ mục {id}" — class `chip-status` (kiểu thẻ đồng nhất) |
| `detail-changing-lines` | `changing_lines` != [] | "Hào N động — quẻ biến: {biếnTen}" — [UI-POLISH t_fc6387df] nằm trong `chip-status text-cinnabar` (cùng kiểu thẻ với chip chỉ mục) |
| `detail-chip-free` | `#1 free_deep===true` | [UI-POLISH t_fc6387df] chip "Luận sâu miễn phí hôm nay" — `chip-status text-bamboo` |
| `detail-tabs` | luôn | hàng 3 tab |
| `detail-tab-{cong-viec,tinh-duyen,tai-loc}` | luôn | đổi tab = đổi `detail-free-slot` (free_content, KHÔNG paywall) |
| `detail-free-slot` | có tab chọn | đoạn luận ngôi đang chọn |
| `detail-dai-ci` | `daiCI` | lời Đại từ |
| `detail-vv-nien` | `vvanNien` | đoạn Vận niên |
| `detail-keywords` | `keywords.length` | chip từ khóa |
| `detail-original-toggle` | `cat` | bật/đóng accordion Bản gốc (Hoàng Kiến CUT-45: FE chỉ render `cat.free` nếu có — KHÔNG có khối gốc nháp) |
| `detail-original-body` | mở | text `cat` |
| `topic-gate` | luôn | vùng "luận sâu" (TopicGate) — 3 nhánh dưới |

### TopicGate (S3) — 3 nhánh 04-ui §2.S3 + §4
| testid | khi nào | nội dung |
|---|---|---|
| `gate-skeleton` | đang gọi #6 | skeleton chờ |
| `gate-locked` + `gate-cta-paywall` | chưa unlock (C-03) | blurb 1 câu + nút → `/mo-khoa/{topic}` |
| `gate-ask` (disabled) + `gate-cooldown` | sau 429 TOPIC_COOLDOWN | đếm ngược `mm:ss` từ `retry_after_seconds`, nút disabled (90s, C-01). Hết đồng hồ → `gate-cooldown` BIẾN MẤT, `gate-ask` enable lại nhãn "Xin luận sâu" (fix E5 t_0285ac01 — QA không còn thấy "— 00:00" vĩnh viễn) |
| `gate-cap` | hết lượt ngày | "Hôm nay hết lượt luận, quay lại sau 0h." (cap ngày) |
| `gate-result` | đã unlock + OK | vùng bài luận: dòng `gate-result-question` (nếu có) + thân bài `{br}` → `<br>`, nút `gate-ask` "Xin luận sâu" còn dùng |
| `gate-result-question` | `phase done` VÀ có câu hỏi đã gửi (LUAN-V2 §7.4.4, t_d4cfddea) | 1 dòng nhỏ `text-small text-muted` ĐẦU bài: `"Bạn hỏi: <nguyên văn đã trim>"`. Nguồn = snapshot câu hỏi lúc bấm gửi (FE-local — payload #6 cố ý KHÔNG có field question, xem QuestionCacheTest chống-leak phía BE). Bỏ trống ô / whitespace-only → KHÔNG render dòng này (kể cả khi khách gõ lại chữ vào ô sau khi gửi). Retry (gate-retry) giữ đúng dòng hỏi của bản gửi đi. |
| `gate-retry` | 429/500 | nút thử lại |
| `gate-failed` | backend fail | "Câu hỏi hơi hóc. Thử lại nhé." (F-03 — KHÔNG bịa nội dung) |
| `gate-question` | `phase idle` (LUAN-V2 t_b13fd2b9) | `<textarea>` ô "Bạn đang vướng chuyện gì?" — placeholder "Bạn đang vướng chuyện gì? (không bắt buộc)", `maxlength=200`, `rows=3`, đặt TRƯỚC nút `gate-ask` trong DOM. Rỗng/whitespace sau trim → payload #5 KHÔNG chứa key `question` (D4 — giữ nhánh cache question-NULL phía BE). Có text → gửi nguyên văn ĐÃ TRIM. Nội dung sống độc lập phase → `gate-retry` giữ nguyên text đã nhập |
| `gate-question-chip` | cùng block idle, 3 cái | [D3] gói gợi ý text theo topic của TAB hiện hành (`QUESTION_SUGGESTIONS` trong constants.js, style `chip-status`) — bấm CHỈ điền text vào `gate-question`, KHÔNG gọi API, KHÔNG đổi topic payload. QA assert: topic trong body #5 luôn == tab đang chọn |
| `gate-question-counter` | cạnh chip | `"{len}/200"` đếm unicode (khớp mb_strlen BE §4.1), `aria-live="polite"`, đổi màu cinnabar khi chạm trần 200 |
| `donate-cta-open` | `#1 free_deep===true` SAU khi luận render xong (F8-FE C3) | [UI-POLISH t_fc6387df] ĐỔI HỢP ĐỒNG: KHÔNG còn "chip y hệt share-card-open" — donate là CTA monetization BẬC 1 `btn-cinnabar` (nền đỏ chữ trắng, nổi bật nhất hàng hành động); share GIÁNG xuống `btn-outline` (viền, không nền độn). Row mang class `has-donate-cta` khi donate hiện → nút TopicGate cũng về outline đồng bộ (donate độc tôn đỏ). Nhãn "Lễ tùy tâm ủng hộ", đặt SAU TopicGate trong footer `detail-actions`; click → track `donate_cta_click` {topic tab hiện hành} + push `/mo-khoa/{topic}?mode=donate`. Flag false/absence → KHÔNG render, KHÔNG bắn `donate_cta_shown` (kể cả entitlements đủ 3 topic — C1: FE không suy từ entitlements) |

## S4 Mở khóa `/mo-khoa/:topic` — PaywallView.vue
| testid | khi nào | nội dung |
|---|---|---|
| `pay-mode-donate` | `query.mode==='donate'` VÀ `#1 free_deep===true` (F8-FE C4) | root div của màn donateMode. donateMode: h1 = "Lễ tùy tâm", `pay-unlock-btn` + `pay-price` + dòng "Trả một lần…" ẤN (CẤM mọi wording 29k/unlock khi free); block donate giữ nguyên. Flag OFF → phớt lờ query, paywall 29k nguyên bản |
| `pay-price` | luôn (trừ donateMode) | "29.000đ" — one-time theo device (C-05); KHÔNG đồng hồ đếm ngược / "còn N suất" |
| `pay-unlock-btn` | luôn (trừ donateMode) | nút 1: POST #7 order → render QR |
| `pay-qr` | có order | QR PNG render client-side bằng lib qrcode (qr_data VietQR, dynamic import) |
| `pay-confirm-link` | `confirm_url` có | stub "tôi đã chuyển" (PAY-01 chưa webhook — QA bấm 1 lần) |
| `pay-status` | sau khi poll | "Chờ thanh toán…" / "Đã nhận được lễ — đang mở…" (poll #9 mỗi 3s, timeout 5') |
| `pay-stub-note` | có order (FE-1 mới) | `stub:true` từ #7 BE-2 → "…chưa thu tiền thật…" (paywall stub — QA KHÔNG assert tiền về) |
| `pay-thanks` | paid → #10 OK | màn cảm ơn + link về S3 |
| `pay-repoll` | paid nhưng #10 chưa thấy | nút "kiểm tra lại" (FE refetch #1) |
| `pay-error` / `pay-retry` | #7/#8/#9 lỗi | thông báo + thử lại (mã lỗi 04-ui §4: `INVALID_TOPIC`, `ALREADY_PAID`, `ORDER_NOT_FOUND`, `ORDER_EXPIRED`) |
| `pay-net-warn` | `navigator.onLine === false` | "Không có mạng…" (mạng chập chờn) |
| `pay-donate-block` / `pay-donate-chip` / `pay-donate-input` / `pay-donate-btn` | luôn, sau blocker | mục phụ "Ủng hộ tác giả": chips 1.000/2.000/5.000đ + input tự do; 50.000đ KHÔNG là option gợi ý (C-07) |

## S5 Sổ quẻ `/cua-ban` — LibraryView.vue
| testid | khi nào | nội dung |
|---|---|---|
| `lib-loading` / `lib-error` / `lib-retry` | gọi #4 / lỗi | trạng thái |
| `lib-empty` | `data.length === 0` | "Chưa có quẻ nào." |
| `lib-item-link` | mỗi dòng | link → `/que/{id}` (timeline NGƯỢC, mới nhất đầu) |
| `lib-item-symbol` / `lib-item-name` / `lib-item-date` | mỗi dòng | symbol nhỏ + `ten` (tra #2, cache module) + `drawn_date` |

## Toàn cục — App.vue / components
| testid | nơi | ghi chú |
|---|---|---|
| `disclaimer-bar` | footer mọi màn | wording §5 từng chữ, QA grep nguyên văn |
| `toast-stack` / `toast-{n}` | overlay | 04-ui §4 |
| `data-line` / `data-position` | mọi nơi dùng LineChart | hào 6→1 top→bottom; QA soi qua wrapper `detail-linechart` |

## External state (QA đối chiếu)
- FE không tự tính ngày: mọi mốc "hôm nay" đến từ `server_date_vn` (#1) / `drawn_date`.
- `data-position` / `data-draw-line` / `data-line` là attribute kỹ thuật — QA đọc để assert thứ tự hào, không render cho khách.
- FE CHỈ hiển thị; enforce thật (1 quẻ/ngày, cooldown 90s, cap 3, unlock) là BE. Test 429 phải mock API.

## Lệnh chạy bằng chứng
```
cd frontend
npm ci
npx vitest run                      # 76 tests / 14 files (E5 t_0285ac01: +topicgate cooldown→idle)
npm run typecheck                   # vue-tsc --noEmit, exit 0
NODE_OPTIONS=--max-old-space-size=1024 npm run build   # → backend/public/app/
```

## FE-1 — gọi API THẬT (card t_db7d18a0)
- 9 hàm `api.*` trong `src/api/client.js` khớp 1-1 đường dẫn `routes/api.php` BE-1 (a4b54a1: #1 #2 #3 #4 #10) + BE-2 (9d22f15: #5 #6 #7 #9). Không còn mock/fixture nào trong `src/` (grep "mock|fake|fixture" = 0 kết quả).
- Draw §3.2 KHÔNG embed hexagram → cache module mới `src/composables/useHexagrams.js` (ensure/prime/get, in-flight dedupe, 1 request/quẻ/phiên). S1/S3/S5 đọc qua cache; S2 prime từ #3 → S3 zero-fetch.
- Deep-link `/que/{id}` quá khứ: resolve qua #4 (limit 50) rồi #2 — không có GET /draws/{id}.
- S4 đọc đúng payload #7 BE-2 (`qr_data`, `confirm_url`, `stub:true` → `pay-stub-note`), poll #9 status ∈ pending/paid/expired/cancelled; paid → refresh #1 → toast + về S3.

## E5 fix (card t_0285ac01) — TopicGate cooldown hết giờ tự mở khoá
- 429 `AI_COOLDOWN` → `gate-cooldown` đếm `mm:ss` như cũ; khi đồng hồ chạm 0: `gate-cooldown` unmount, `gate-ask` hiện lại ENABLED nhãn "Xin luận sâu" (không còn "— 00:00" disabled vĩnh viễn).
- Bấm lại sau cooldown → POST #5 với `idempotency_key` KHÁC (uuid mới mỗi lần) → `gate-skeleton` → poll #6.
- QA assert bằng 2 selector có sẵn: `gate-cooldown` count=0 && `gate-ask` not disabled sau ~90s (ca E5 trong e2e_final.py).

## FE-3XU (card t_463d700d) — nghi thức PA1 + vùng Từ hào S3

### S2 — MagicSequence PA1 (thay 3 bước cũ, gate t_04394e77 chốt PA1)
| selector | nơi | ghi chú |
|---|---|---|
| `data-testid="magic-sequence"` | wrapper nghi thức | `:data-fly-count` = số cụm đã phóng (0 khi prefers-reduced-motion) |
| `data-testid="ritual-stage"` | sân khấu 6 hào (trong `magic-sequence`) | mỗi slot `data-position="1..6"` (1=sơ ở ĐÁY) + `data-draw-line` + class `is-shown` khi hào đã vẽ (mốc drawAt[i]) |
| `data-testid="coin-cluster"` | cụm xu bay | `:data-fly` = index; đếm = số cụm tại mốc; reduced-motion = 0 |
| `data-testid="draw-status"` | dòng chữ dưới sân khấu | word-by-beat PA1 (`statusAt`): "Vê nhẹ bó xu…" → "Tung xu — hào 1" (260) → "Hào i · 6" (drawAt) → "Hào 1·6 động — dấu 動" (2560) → "Địa Thiên Thái ䷊" (3060, API chưa về = "Đang mở quẻ…"); `aria-live="polite"`; QA assert KHÔNG rỗng mọi thời điểm |
| `data-testid="dyno-badge"` | dấu 動 ở hào động (trong slot của `ritual-stage`) | chỉ tồn tại trên slot `r.mov`; class `show` bật tại dynoAt=2560 (TRƯỚC reveal 3060); text `動` |
| `data-testid="reveal-hexagram"` | symbol + tên quẻ | xuất hiện đúng revealAt=3060ms && có `ten` (API #3 về); chứa symbol ䷊ + tên |
| `data-testid="reveal-sub"` | dòng dưới reveal | chỉ nhắc hào động: "hào 1·6 động" — CẤM quẻ biến (gate t_04394e77) |
| `data-testid="draw-frame"` | DrawView wrapper S2 | stage machine |
| `data-testid="draw-start"` | nút bấm gieo (idle) | một-chạm |
| `data-testid="draw-spinner"` | pending sau done | API #3 chưa về |
| `data-testid="draw-result"` | khối kết quả | hiện khi `result && done` |
| `data-testid="draw-error"` + `data-testid="draw-retry"` | lỗi #3 | `role="alert"` + nút "Gieo lại" |

- Lịch PA1 thuần nằm ở `src/utils/timeline.js` (pa1Timeline/drawAt/flyAt/statusAt) — UT `tests/timeline.test.js` chốt từng mốc; component chỉ render bám lịch.

### S3 — vùng "Luận hôm nay" (04-ui §S3, nguồn #3 prime / #2b fetch)
| selector | nơi | ghi chú |
|---|---|---|
| `luan-hom-nay` | `<section>` cả vùng | heading "Luận hôm nay" nằm trong; QA grep nguyên văn nhãn. [UI-POLISH t_fc6387df] render qua `LuanHomNay.vue` — nhịp 3 tầng: đại ý (tiểu dẫn) → kicker "TỪ HÀO" → khối từ hào (kết) |
| `luan-dai-y` | dòng Đại ý dưới heading | ≥1 hào động: Đại ý + đủ N khối từ hào; 0 hào động: CHỈ Đại ý |
| `luan-hao-label` | kicker chỉ khi ≥1 hào động | [UI-POLISH t_fc6387df] nhãn "Từ hào" hoa + giãn chữ (`.chip-kicker`, text-muted đạt AA), tách nhịp giữa đại ý và khối hào; 0 hào động → KHÔNG render |
| `luan-hao-list` | wrapper danh sách khối | đếm con == số `changing_lines` (0 hào động = list rỗng, không khung trống) |
| `data-testid="hao-dong-block"` | card 1 khối / hào động | `:data-vi` = vị trí (1..6), sắp xếp sơ→thượng |
| `data-testid="hao-dong-label"` | nhãn hào | "Sơ cửu/Cửu nhị/…/Thượng lục" (trường `hao`) |
| `data-testid="hao-dong-han"` | chữ Hán | nguyên văn `han` |
| `data-testid="hao-dong-quocam"` | Quốc âm | `quoc_am` |
| `data-testid="hao-dong-nghia"` | nghĩa Việt | `nghia` |

- API mới `api.haoTexts(id)` = GET `/api/hexagrams/{id}/hao-texts` (#2b — 03-api §2b); cache `useHaoTexts` (ensure/prime/get) — #3 embed `data.hao_texts` → S3 zero-fetch từ S2; deep-link gọi #2b. #2b fail → vùng từ hào im, Đại ý vẫn render (04-ui §4).
- Text FE không chứa "quẻ biến"/"biến quẻ" ở mọi state (grep test).

### Bằng chứng FE-3XU
```
npx vitest run        # 113 tests / 16 files pass (baseline 83 → +30)
npm run typecheck     # vue-tsc --noEmit exit 0
npm run build         # ok → backend/public/app
# AC4: 3 ảnh thật (Chrome 151 headless CDP, mock-API 5300, data dataset pin b6216b49):
# /data/agents/fe-dev/outbox/t_463d700d/ac4-s2-dang-gieo.png   (hào 2·6, fly, chưa lộ symbol)
# .../ac4-s2-reveal.png                                        (3.06s: ䷊ + Địa Thiên Thái + 動, không quẻ biến)
# .../ac4-s3-tu-hao.png                                        (Luận hôm nay: Đại ý + Cửu nhị 九二：包荒… đủ 4 dòng)
# tool: outbox/t_463d700d/shot_server.py + shot_ac4.py
```

---

## F7 — Thẻ chia sẻ (share card) — card t_2e969791

Nguồn: SPEC-THE §1/§3, MOCKUP-CARD, F7-CONTRACT §5 (7 testid), METRICS V1–V4.

### S3 — nút mở overlay (DetailView)
| selector | nơi | ghi chú |
|---|---|---|
| `data-testid="share-card-open"` | chip `btn-outline` (BẬC 2 — nhẹ hơn donate một bậc, [UI-POLISH t_fc6387df]), cuối S3 trong `detail-actions` | `<button type="button">` nhãn "Chia sẻ thẻ quẻ"; CHỈ xuất hiện trong `template v-else` sau khi #3/#2 render xong (loading/error → không nút, không popup); click → `/share-card?draw={id}` |
| `data-testid="detail-actions"` | có dữ liệu | [UI-POLISH t_fc6387df] hàng hành động cuối S3; mang class `has-donate-cta` khi donate hiện (freeDeep) — QA đo: đúng 1 nút `btn-cinnabar` trong hàng khi class có mặt |

### Overlay `/share-card` (ShareCardView)
| selector | nơi | ghi chú |
|---|---|---|
| `data-testid="share-card-open"` | root overlay `fixed inset-0` | mount là bắn V1 `share_card_open` {draw_id, hexagram_id, has_dynamic_line} |
| `data-testid="share-card-image"` | `<img>` PNG canvas thật (dataUrl) | chỉ tồn tại khi render OK; đổi theo khung đang chọn |
| `data-testid="share-card-fallback"` | thẻ HTML tối giản (E1) | xuất hiện KHI `renderFrame` throw → kèm V3 `share_card_error` {draw_id, reason}; Copy link vẫn chạy |
| `data-testid="share-card-frame-9x16"` | toggle story (mặc định on) | `aria-pressed` true/false; đổi khung → renderFrame lần đầu khung đó bắn V2 `share_card_created` {draw_id, frame:"9x16"\|"1x1", render_ms} |
| `data-testid="share-card-frame-1x1"` | toggle feed | cùng cơ chế V2 với frame:"1x1" |
| `data-testid="share-card-download"` | nút Tải ảnh | disabled khi chưa có shot; PNG tên `que-{token}.png`; success → V4 `share_card_done` {draw_id, method:"download", token} |
| `data-testid="share-card-copy-link"` | nút Copy link | 9:16 = NGUYÊN URL `/s/{token}`; 1:1 = `CAPTION_1X1 + "\n" + URL` (CAP-THE §4); clipboard success → V4 method:"copy" |
| `data-testid="share-card-native"` | nút Chia sẻ (Web Share) | `navigator.share({files:[PNG], text: CAPTION_NATIVE render tên quẻ})`; success → V4 method:"native"; E2 cancel/unsupported → IM LẶNG, không done, không alert |
| `data-testid="share-card-close"` | chữ "Đóng" đáy overlay | router.replace về detail |

- Link fail (BE F7 chưa merge / 503): thẻ VẪN vẽ, url dự phòng `{origin}/que/{id}`, token null, KHÔNG bắn `share_card_error` (không chặn UX).
- API mới: `api.shareLinks(draw_id)` POST `/api/share-links` {draw_id}; `api.shareCard(token)` GET `/api/share-links/{token}` (client chỉ khai báo route; view /s/{token} Blade do lane khác).
- Renderer: `src/utils/shareCardCanvas.js` canvas 2D TỰ VẼ (không html2canvas), đơn vị 1080, safe-area story 250/310; logic content `src/utils/shareCard.js` (TH1 hào động đầu tiên → TH2 vế đầu dai_ci → E6 tối giản; hook ≤80, text_1x1 ≤60, clip tại ranh giới câu/từ, không cắt giữa từ).
- Tracking: `src/utils/track.js` fire-and-forget POST `/api/track`, fail im lặng, tên V1–V4 NGUYÊN VĂN không prefix (F7-CONTRACT §1).
