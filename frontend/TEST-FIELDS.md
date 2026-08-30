# TEST-FIELDS — FE-0 · mapping màn hình → data-testid → ý nghĩa

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
| `home-hexagram-symbol` | đã gieo | glyph `hexagram.symbol` (䷊) |
| `home-hexagram-name` | đã gieo | `ten` + `han` → "Địa Thiên Thái 泰" |
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
| `draw-result` | T ≥ 1.5s VÀ #3 về | "{ten} — đang vào bảng giải…" → 0.6s sau auto-push S3 |
| `disclaimer-bar` | luôn | App shell |

DRAW_LIMIT_REACHED (409) → replace về `/` + `?toast=draw_limit` → S1 render `toast-stack` / `toast-{n}` nội dung "Hôm nay đã gieo rồi, hẹn 0h."

## S3 Kết quả `/que/:drawId` — DetailView.vue
| testid | khi nào | nội dung |
|---|---|---|
| `detail-loading` / `detail-error` | gọi #5 / lỗi | trạng thái, không trắng màn |
| `detail-linechart` | có dữ liệu | LineChart 6 hào; `data-line="0\|1"` trên→dưới = hào 6→1; `data-position="1..6"`; hào động (6/9) thêm chấm `dot` + chữ "động" |
| `detail-hexagram-name` | có dữ liệu | `ten` + `han` + symbol |
| `detail-changing-lines` | `changing_lines` != [] | "Hào N động — quẻ biến: {biếnTen}" |
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
| `gate-ask` (disabled) + `gate-cooldown` | sau 429 TOPIC_COOLDOWN | đếm ngược `mm:ss` từ `retry_after_seconds`, nút disabled (90s, C-01) |
| `gate-cap` | hết lượt ngày | "Hôm nay hết lượt luận, quay lại sau 0h." (cap ngày) |
| `gate-result` | đã unlock + OK | đoạn AI, `{br}` → `<br>`, nút `gate-ask` "Xin luận sâu" còn dùng |
| `gate-retry` | 429/500 | nút thử lại |
| `gate-failed` | backend fail | "Câu hỏi hơi hóc. Thử lại nhé." (F-03 — KHÔNG bịa nội dung) |

## S4 Mở khóa `/mo-khoa/:topic` — PaywallView.vue
| testid | khi nào | nội dung |
|---|---|---|
| `pay-price` | luôn | "29.000đ" — one-time theo device (C-05); KHÔNG đồng hồ đếm ngược / "còn N suất" |
| `pay-unlock-btn` | luôn | nút 1: POST #7 order → render QR |
| `pay-qr` | có order | QR PNG render client-side bằng lib qrcode (qr_data VietQR, dynamic import) |
| `pay-confirm-link` | `confirm_url` có | stub "tôi đã chuyển" (PAY-01 chưa webhook — QA bấm 1 lần) |
| `pay-status` | sau khi poll | "Đang chờ xác nhận…" / "Đã mở khóa." |
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
npx vitest run                      # 42 tests / 9 files
npm run typecheck                   # vue-tsc --noEmit, exit 0
NODE_OPTIONS=--max-old-space-size=1024 npm run build   # → backend/public/app/
```
