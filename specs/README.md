# specs/ — Thư mục spec công ty

## Luật đánh số (boss chốt 30/08/2026)
Mỗi spec = **1 folder** `N.{slug}` — N đánh số tăng dần, KHÔNG đổi tên folder cũ.

| Folder | Nội dung |
|---|---|
| `1.mvp/` | SPEC MVP Quẻ Hôm Nay: 01-overview, 02-db, 03-api, 04-ui, 05-testplan |

Quy ước file trong folder: `0X-<tên>.md` — 5 file chuẩn: overview / db / api (kèm CONTRACT) / ui / testplan.

## Nâng cấp / chỉnh sửa — viết TIẾP, không viết lại từ đầu
1. **Sửa nhỏ trong scope spec hiện tại** → edit trực tiếp file + thêm dòng vào `CHANGELOG.md` của folder: `- [N] YYYY-MM-DD · card t_xxxx · tóm tắt đổi gì + tại sao`.
2. **Đổi hướng/phạm vi lớn** (thêm sản phẩm phụ, đổi pricing model, đổi stack) → MỞ folder mới `N+1.{slug}` kế thừa, đầu file ghi rõ `Kế thừa 1.mvp — thay đổi: ...`; folder cũ giữ nguyên làm hồ sơ.
3. Card dev/QA body luôn trỏ **đường dẫn folder spec** (`specs/1.mvp/03-api.md`), không trỏ mơ hồ "xem spec".
4. ux-ui: mockup nào chốt theo spec nào → ghi vào DESIGN-NOTES.md kèm số folder.
