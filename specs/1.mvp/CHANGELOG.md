# CHANGELOG — 1.mvp

- [0] 2026-08-30 · card t_dd375e7c (SPEC-01) · bản gốc 5 file, tái lập từ hồ sơ CEO (file cũ mất khỏi đĩa)
- [0.1] 2026-08-30 · t_dd375e7c · chốt cap 90s vs 90 lần (C-03); sửa typo '预留'→'chừa'; đổi đường dẫn seed → backend/database/data/hexagrams.json
- [0.2-SPEC-3XU] 2026-08-31 · t_a60997f8 · branch feat/3xu (CHƯA merge main, chờ boss): roller đổi về 3 đồng xu chuẩn 12.5/37.5/37.5/12.5 (C-09, 03-api §3.1 — bác cỏ-thi 44/88/94); luật luận 0/nhiều hào động + từ hào, quẻ biến tính-lưu-KHÔNG-UI-KHÔNG-prompt (01 §4bis, 04-ui S3); bảng mới hexagram_hao_texts 64×6 + seeder + endpoint #2b + field hao_texts trong #3 (02-db §4b — ADR-004 chọn bảng riêng, trade-off ghi tại chỗ); testplan: U4 statistics ≥200k ±5σ, U7 384 ô không rỗng, F9 shape hao_texts, E2/E7 mới. Code chưa đụng — BE/FE card sau.
