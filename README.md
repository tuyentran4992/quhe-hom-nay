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

## Luật cộng tác trong repo này
- Branch per card: `card/<id>` từ `main`; **chỉ dev-lead merge main**.
- be-dev ghi `backend/**`, fe-dev ghi `frontend/**`; không cross.
- API sửa = sửa 03-api.md trước (dev-lead), code sau.
- Không commit: `.env`, API key, build output.

Khởi tạo: dev-lead, card t_73a729be (30/08/2026) — commit đầu chỉ .gitignore + README,
scaffold Laravel/Vite là việc của BE-0/FE-0.
