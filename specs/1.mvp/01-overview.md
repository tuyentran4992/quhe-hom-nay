# 01 · OVERVIEW — Quẻ Hôm Nay (SPEC-01, tái lập chính thức)

Card: t_dd375e7c · dev-lead · 30/08/2026. Bản này THAY cho spec cũ t_73a729be (file đã mất
khỏi đĩa, chỉ còn hồ sơ CEO). Quyết định đã khóa của CEO/QV trong hồ sơ không đổi; điểm lệch
cũ "cap 90s vs cap 90 lần" được CHỐT tại đây và ở 03-api §0 (xem C-03).

## 1. Sản phẩm là gì

Web (không app) "Chiêm nghiệm phương Đông hàng ngày": mỗi ngày gieo 1 quẻ Kinh Dịch miễn phí,
luận giải cơ bản 3 ngôi (công việc / tình duyên / tài lộc) bằng content có sẵn; luận giải
chuyên sâu theo chủ đề (duyên / tài lộc / xuất hành) sinh bằng AI-Box, unlock one-time 29.000đ
qua payOS VietQR + nút "Lễ tùy tâm". Wording BẮT BUỘC toàn sản phẩm: "giải trí / tham khảo
văn hoá". CẤM: bán ritual (cúng/giải hạn/bùa), câu chữ "thay đổi vận mệnh".

## 2. Stack & kiến trúc tổng thể

- `backend/` — Laravel 11 monolith (PHP 8.3), API JSON + phục vụ SPA tĩnh.
- `frontend/` — Vue 3 + Vite + Tailwind SPA, build ra `backend/public/app/` (không commit).
- DB — MariaDB 10.11, database `quhe_hom_nay`, queue + sessions + jobs đều qua DATABASE.
- AI — AI-Box (provider LLM cấu hình qua env), gọi CHỈ từ queue worker, không gọi đồng bộ
  trong request HTTP.

Kiến trúc hexagonal nhẹ: `app/Domain/` (pure PHP, không import facade/HTTP), `app/Services/`
orchestration, `app/Http/` + `app/Jobs/` adapter. Bất biến nghiệp vụ (định giá, cooldown,
cấu trúc 3 ngôi) đặt 1 chỗ: `backend/config/project.php` (mọi SỐ đổi được — CFG-BE 02/09) + `app/Domain/Rules.php` (enum cấu trúc: TOPICS, coin, MAGIC) + service tương ứng.

```
Browser (Vue SPA)
   │ fetch /api/*  (cookie session, SameSite=Lax)
   ▼
Laravel API ──► MariaDB (8 bảng, xem 02-db)
   │  dispatch job 'interpret'
   ▼
queue:work --database ──► AI-Box HTTP ──► kết quả ghi ai_jobs.result
```

## 3. Cây thư mục dự kiến (BE-0/FE-0 scaffold theo đây)

```
quhe-hom-nay/
├── specs/                      # 5 file này = LUẬT
├── backend/                    # Laravel 11
│   ├── app/
│   │   ├── Domain/Rules.php    # enum cấu trúc; SỐ/CỜ nghiệp vụ: config/project.php (bảng C ở 03-api §0)
│   │   ├── Http/Controllers/   # Draw, Hexagram, Interpretation, Payment, Me
│   │   ├── Http/Middleware/    # EnsureDeviceSession, IdempotencyKey
│   │   ├── Jobs/RunAiBoxJob.php
│   │   ├── Models/             # Eloquent: User Device Session Hexagram HexagramHaoText Draw Payment AiJob
│   │   └── Services/           # DrawService InterpretationService PaymentService
│   ├── database/migrations/    # 8 bảng theo 02-db, tên file 0001..0008
│   ├── database/data/
│   │   ├── hexagrams.json      # content 64 quẻ đã QV-chốt, canSoi đã strip ✓ đã commit (sha256 76cfc11f…) — CẤM sửa (SPEC-3XU)
│   │   └── hao_texts.json      # SPEC-3XU: 64×6 từ hào, ghép từ research hao_texts_part1..4 (seed riêng, xem 02-db §9)
│   ├── database/seeders/
│   │   ├── DatabaseSeeder.php  # gọi HexagramSeeder + HaoTextSeeder (đều idempotent)
│   │   ├── HexagramSeeder.php  # đọc ../data/hexagrams.json
│   │   └── HaoTextSeeder.php   # đọc ../data/hao_texts.json (SPEC-3XU)
│   ├── routes/api.php
│   └── .env.example            # đủ biến mục 5, không có giá trị thật
├── frontend/                   # Vue 3 + Vite + Tailwind
│   ├── src/
│   │   ├── api/                # client sinh theo 03-api (khớp từng field)
│   │   ├── composables/        # useDeviceSession useDraw usePoll
│   │   ├── views/              # Home Draw Detail Paywall Library (04-ui)
│   │   └── components/         # MagicSequence (≥1.5s), HexagramLines, DisclaimerFooter
│   └── vite.config.js          # outDir: ../backend/public/app
└── README.md
```

## 4. Luồng chính MVP

1. Khách lạ vào → middleware gán device (`POST /api/devices` ẩn trong EnsureDeviceSession:
   cookie `qhn_device` + row `devices`; Laravel session cookie riêng để sweep) → `GET /api/me`.
2. `POST /api/draws` (gieo quẻ): server gieo 6 hào bằng **3 đồng xu chuẩn** (mỗi hào 3 xu
   độc lập, sấp=2 ngửa=3, tổng ∈ {6,7,8,9}; CSPRNG — thuật toán chốt ở 03-api §3.1, C-09),
   tra `hexagrams`, ghi `draws`,
   trả quẻ + luận 3 ngôi (content tĩnh, KHÔNG qua AI). Limit: 1 quẻ/ngày (C-01).
3. Khách bấm "Xin luận sâu" → `POST /api/ai/interpretations` (chủ đề duyên|tai_loc|
   xuat_hanh) → 402 nếu chưa unlock; đã unlock hoặc sau khi payOS webhook OK thì tạo
   `ai_jobs` pending, worker gọi AI-Box, client poll `GET /api/ai/jobs/{job_uuid}` (2s/lần).
4. Thanh toán: `POST /api/payments/create` (stub, PAY-01 đổ code thật) → VietQR → webhook
   → entitlement ghi vào `payments.status=paid` → unlock cho device đó, chủ đề đã mua, vô hạn.

### 4bis. Luật "luận" SPEC-3XU (boss chốt 31/08 — áp dụng S3 + prompt AI)

- 0 hào động → luận theo ĐẠI Ý quẻ gốc (`dai_ci`).
- ≥1 hào động → ĐẠI Ý quẻ gốc + TỪ HÀO của từng hào động (xếp sơ→thượng, nguồn
  `hexagram_hao_texts`, 02-db §4b).
- Quẻ biến vẫn tính + lưu DB (nghiên cứu sau), NHƯNG không hiển thị UI và không đưa vào
  prompt AI-Box. Prompt #5 chỉ gồm: đại ý quẻ gốc + các từ hào động + topic + free_content
  liên quan.

## 5. Env (`.env.example` phải đủ — điền là chạy)

| Biến | Ý nghĩa | Ví dụ an toàn để commit |
|---|---|---|
| `DB_DATABASE` | tên DB MariaDB | `quhe_hom_nay` |
| `DB_USERNAME` / `DB_PASSWORD` | tài khoản DB | `quhe_app` / `` (TRỐNG, không commit key) |
| `QUEUE_CONNECTION` | bắt buộc | `database` |
| `SESSION_DRIVER` | bắt buộc | `database` |
| `AIBOX_API_KEY` | key AI-Box | KHÔNG commit giá trị; reader chú thích |
| `AIBOX_BASE_URL` | endpoint provider | `https://api.example-aibox.test/v1` |
| `AIBOX_MODEL` | model luận giải | `aibox-default` |
| `PAYOS_CLIENT_ID` / `PAYOS_API_KEY` / `PAYOS_CHECKSUM_KEY` | payOS | placeholder rỗng |
| `PAYOS_WEBHOOK_SECRET` | verify IPN | placeholder rỗng |

Hằng số nghiệp vụ (cooldown 90 giây, 1 quẻ/ngày, timeout 120 giây, retry 3, cap 90 job/giờ,
giá 29000đ) KHÔNG nằm trong env — nằm trong `backend/config/project.php` theo 03-api §0 để FE/BE
đối chiếu một mối.

## 6. Số khóa (đọc trước khi code — chi tiết ở 03-api §0)

C-01 free 1 quẻ/device/ngày · C-03 cooldown xin lại luận sâu 90 giây/device (CHỐT: là 90
GIÂY thời gian, không phải 90 lần; đây là điểm lệch bản cũ) · C-04 job AI timeout 120 giây,
retry 3 lần · C-06 cap toàn cục 90 job AI/giờ · C-05 giá unlock 29.000đ one-time · C-09 gieo
mỗi hào = 3 đồng xu (sấp 2 / ngửa 3). Magic sequence FE tối thiểu 1.5 giây (04-ui).

## 7. Phân kỳ & quyền merge

BE-0 (scaffold+migrate+seed) → BE-1 (draw+hexagram API) ∥ BE-2 (AI+payment API) →
FE-0 (scaffold+tokens) → FE-1 (5 màn hình). PAY-01 (payOS thật) là sóng 2 — spec này đã
chừa contract (endpoints #6 #7). Branch per card `card/<id>`, chỉ dev-lead merge main.
