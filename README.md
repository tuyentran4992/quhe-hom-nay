# Quẻ Hôm Nay (rebuild)

Gieo một quẻ Kinh Dịch mỗi ngày — free, "lễ tùy tâm", premium casual.

**Cổng spec (đọc trước khi code):** `specs/` trong repo này — đường dẫn chính thức từ SPEC-01 (t_dd375e7c).
01-overview (kiến trúc, cây thư mục, env) · 02-db (DDL + seeder contract) ·
03-api (contract endpoint — LUẬT, FE/BE code khớp từng field) · 04-ui (màn + design tokens) ·
05-testplan (ca test + lệnh preview).

## Stack
Laravel 11 monolith (`backend/`) · Vue 3 + Vite + Tailwind SPA (`frontend/`, build ra
`backend/public/app/`) · MariaDB 10.11 (`quhe_hom_nay`) · AI-Box qua queue DATABASE.

## Preview local
```bash
cd backend && php artisan migrate:fresh --seed && php artisan serve --port=8000
cd frontend && npm ci && NODE_OPTIONS=--max-old-space-size=1024 npm run build
```

## Deploy checklist pilot 02/09 (FREE_DEEP_PREVIEW)
**SHA deploy = `main` (UI-POLISH t_fc6387df đã merge @ a00db70 01/09 — preview boss
duyệt trên Cloudflare CHÍNH LÀ code main, không còn branch lẻ cần hợp nhất trước deploy).**
Flag gate luận sâu: `backend/config/preview.php` đọc `FREE_DEEP_PREVIEW` (mặc định
`false` = paywall 29k). Pilot 02/09 chạy FREE (CEO chốt t_3a656b1b, luật anh Tuyền 31/08)
→ **set TRUE**, không có bước này = vô tình bật paywall.

```bash
# 1. .env trên máy deploy: thêm dòng (cả app chạy queue worker cũng phải thấy env này)
echo 'FREE_DEEP_PREVIEW=true' >> backend/.env
# 2. Dump lại config (env đọc lúc config:cache, sửa .env không cache lại = flag không đổi)
cd backend && php artisan config:cache && php artisan queue:restart   # restart worker ăn config mới
# 3. Verify GET /me — kỳ vọng entitlements = đủ 3 topic duyen,tai_loc,xuat_hanh
curl -sc /tmp/deploy.jar https://<host>/api/me | jq '.entitlements'
# 4. Verify 1 luận sâu KHÔNG bị 402 (lệnh đầu = 202 queued là ĐẠT; 200 = replay/done.
#    draw_id lấy từ POST /draws bằng cùng cookie jar; cooldown C-03 90s giữa 2 lần gọi)
curl -sb /tmp/deploy.jar -X POST https://<host>/api/draws -H 'Content-Type: application/json' -d '{}' | jq '.data.draw.id'
curl -sb /tmp/deploy.jar -o /dev/null -w '%{http_code}\n' -X POST https://<host>/api/ai/interpretations \
  -H 'Content-Type: application/json' -d '{"draw_id":<id>,"topic":"duyen","idempotency_key":"deploy-check-1"}'
# 5. Sau pilot tắt flag: đặt FREE_DEEP_PREVIEW=false → config:cache → queue:restart (QA lại bản 29k)
```
Cả 4 lệnh verify phải khớp kỳ vọng trước khi báo CEO khép chuỗi deploy.

## Luật cộng tác trong repo này
- Branch per card: `card/<id>` từ `main`; **chỉ dev-lead merge main**.
- be-dev ghi `backend/**`, fe-dev ghi `frontend/**`; không cross.
- API sửa = sửa 03-api.md trước (dev-lead), code sau.
- Không commit: `.env`, API key, build output.

Khởi tạo: dev-lead, card t_73a729be (30/08/2026) — commit đầu chỉ .gitignore + README,
scaffold Laravel/Vite là việc của BE-0/FE-0.
