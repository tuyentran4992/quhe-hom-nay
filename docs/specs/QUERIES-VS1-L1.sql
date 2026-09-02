-- VS1-L1 (SPEC-VS1-L1 §4) — 3 query tổng hợp funnel chia sẻ, growth copy-paste.
-- Nguồn CHUẨN = cột devices.referred_token (bền, có index); props V7 là bản
-- ảnh đối chiếu chéo. Backward compat: event V7 cũ chỉ {draw_id} hợp lệ mãi
-- mãi → mọi query chạy đúng trên dữ liệu trộn cũ+mới (nhóm token NULL riêng,
-- không crash, không loại dòng). Chứng minh chạy được: T4
-- backend/tests/Feature/Api/ShareAttributionTokenTest.
--
-- ? = ngày biên (drawn_date / first_seen, kiểu DATE/DATETIME). Anti-rule D14:
-- dòng k chỉ tô màu khi V4 >= 50 device (bảng đo, không phải code).

-- Q1 — share_rate (METRICS §2)
SELECT COUNT(DISTINCT CASE WHEN e.name='share_card_done' THEN e.device_id END) AS v4_devices,
       COUNT(DISTINCT d.device_id) AS draw_devices,
       ROUND(100 * COUNT(DISTINCT CASE WHEN e.name='share_card_done' THEN e.device_id END)
             / NULLIF(COUNT(DISTINCT d.device_id),0), 1) AS share_rate_pct
FROM draws d
LEFT JOIN events e ON e.device_id = d.device_id
WHERE d.drawn_date BETWEEN ? AND ?;

-- Q2 — k (device MỚI có V7 / device có V4; "mới" = first_seen trong kỳ)
SELECT COUNT(DISTINCT CASE WHEN e.name='share_referred_draw' AND dv.first_seen BETWEEN ? AND ?
                           THEN e.device_id END) AS new_v7_devices,
       COUNT(DISTINCT CASE WHEN e.name='share_card_done' THEN e.device_id END) AS v4_devices
FROM devices dv LEFT JOIN events e ON e.device_id = dv.device_id;

-- Q3a — draws_per_token, BẢN CỘT (canonical, đếm vòng lặp theo collect card gốc)
SELECT referred_token, COUNT(*) AS referred_devices
FROM devices WHERE referred_token IS NOT NULL
GROUP BY referred_token ORDER BY referred_devices DESC;

-- Q3b — draws_per_token, BẢN ĐỐI CHIẾU props V7 (chứng minh backward compat:
-- dòng cũ gộp vào token NULL, không mất). MySQL: JSON_EXTRACT trên props=NULL
-- trả NULL → NULLIF mới tính; không crash.
SELECT JSON_UNQUOTE(JSON_EXTRACT(e.props,'$.token')) AS token,
       COUNT(DISTINCT e.device_id) AS referred_devices
FROM events e WHERE e.name='share_referred_draw'
GROUP BY token ORDER BY referred_devices DESC;
