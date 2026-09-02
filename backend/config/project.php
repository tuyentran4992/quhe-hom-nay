<?php

/*
|--------------------------------------------------------------------------
| CƠ + SỐ NGHIỆP VỤ — NGUỒN KỸ THUẬT DUYỆT NHẤT (CFG-BE t_ce2a6834, boss chốt 02/09)
|--------------------------------------------------------------------------
| Boss đổi GIÁ TRỊ trong file này là code chạy theo — KHÔNG sửa file code nào khác.
| Luật thường trực: số/cờ nghiệp vụ mới → vào đây, cấm khai sinh hardcode ở Rules/app.
|
| SAU KHI ĐỔI TRÊN SERVER:  php artisan config:clear   (Laravel cache config;
| không clear thì đổi file không có tác dụng — đây là lỗi quên phổ biến nhất).
|
| Quy ước mỗi dòng: TÊN => giá trị  // ý nghĩa · đơn vị · giá trị an toàn.
| Hàm env duy nhất được phép ở file này = FREE_DEEP_PREVIEW (override preview
| chủ đích, mặc định nghiệp vụ vẫn là giá trị dưới). Bí mật (key/token) KHÔNG
| bao giờ xuất hiện đây — chúng ở .env.
|
| Enum cấu trúc (TOPICS) ở App\Domain\Rules; animation FE (MAGIC_SEQUENCE_MS)
| ở frontend/src/constants.js — xem ghi chú đầu Rules.php.
*/

return [

    // Cờ pilot "luận sâu free": true = bỏ qua gate 402 cho mọi device (VẪN giữ
    // lock 1 lượt / cooldown / cap / idempotency). Đơn vị: bool.
    // Giá trị an toàn: false = paywall 29k nguyên bản đã QA. env chỉ dành cho
    // preview có chủ đích (máy dev/deploy pilot), hết pilot là xóa dòng env.
    'free_deep_preview' => env('FREE_DEEP_PREVIEW', false),

    // ---------------------------------------------------------------- AI-Box
    'ai' => [
        // C-04: 1 job được thử tối đa bao nhiêu lần trước khi failed vĩnh viễn.
        // Đơn vị: lần. An toàn: 2–5 (quá cao = đốt tiền provider khi upstream lỗi).
        'max_attempts' => 3,

        // C-04: 1 call provider chết sau bao nhiêu giây. Đơn vị: giây.
        // An toàn: 60–180. Lưu ý: hard-kill worker = timeout + 30 (RunAiBoxJob).
        'timeout_seconds' => 120,

        // C-03: cooldown GIỮA 2 lần xin luận sâu của 1 device. Đơn vị: GIÂY thời
        // gian (không phải "90 lần"). An toàn: 30–300.
        'cooldown_seconds' => 90,

        // C-06: cap TOÀN CỤC job AI tạo mới trong 60 phút gần nhất. Đơn vị: job.
        // An toàn: 30–150 (đòn chống cháy túi provider, không phải chống user).
        'global_cap_per_hour' => 90,

        // FIX-LUAN-SAU: số lần model được TỰ SINH LẠI khi bài dính wordguard
        // (AI_FILTERED) trong cùng 1 handle. Đơn vị: lần. An toàn: 0–2.
        'filter_regenerations' => 1,

        // FIX-LUAN-SAU: lượt đầu hoàn tất trong ngân sách này mới đáng regenerate
        // (FE poll tối đa 130s). Đơn vị: giây. An toàn: 30–60.
        'filter_regenerate_budget_s' => 45,

        // BUG-V3-1: model router danh mục — BẮT BUỘC non-reasoning (model reasoning
        // nhét nhãn sau lý lẽ → content rỗng). Đơn vị: tên model. Muốn đổi model:
        // probe thật trước (đo < router_timeout_seconds) rồi mới sửa đây.
        'router_model' => 'qwen3.6-flash',

        // BUG-V3-1: budget token cho router phát động. Đơn vị: token. An toàn:
        // 8 nếu model non-reasoning; đổi sang reasoning → PHẢI ≥192 hoặc router
        // chết im lặng (log aibox.router.result finish=length là tín hiệu).
        'router_max_tokens' => 8,

        // LUAN-V3 §5.2: timeout RIÊNG bước router (không đụng cap/cooldown luận).
        // Đơn vị: giây. An toàn: 5–15.
        'router_timeout_seconds' => 10,

        // REVIEW-LUAN (boss GO 02/09): khóa MỖI (quẻ, chủ đề) 1 lượt luận — POST
        // trùng khi đã có bài done → 409 AI_ALREADY_DONE, FE sang "Xem lại".
        // bool. =false → quay về đường cũ (cooldown/cap vẫn giữ); chỉ tắt khi
        // boss đổi luật, bật lại ngay sau thử nghiệm.
        'lock_one_luan' => true,
    ],

    // ------------------------------------------------------------------- Giá
    'price' => [
        // C-05: giá one-time mỗi chủ đề luận sâu. Đơn vị: ĐỒNG VND chẵn (không
        // float, không "29k"). Server GHI ĐÈ mọi amount client gửi (PaymentService)
        // — đổi đây là API #7 + lỗi 402 đổi theo, FE đọc lại từ #1/#5.
        'unlock_vnd' => 29000,
    ],

    // ---------------------------------------------------------------- Donate
    'donate' => [
        // C-07: "Lễ tùy tâm" — số tiền TỐI THIỂU device được gửi. Đơn vị: đồng.
        // An toàn ≥ 1000 (dưới = phí payment ăn hết lễ).
        'min_vnd' => 1000,

        // C-07: số tiền TỐI ĐA mỗi lần donate (chống gõ nhầm số to bất thường qua
        // API công khai). Đơn vị: đồng.
        'max_vnd' => 500000,
    ],

    // ------------------------------------------------------------------ Draw
    'draw' => [
        // C-01: số quẻ free / device / ngày dương lịch VN (enforce bằng
        // uq_draws_device_date + gate DrawService). Đơn vị: quẻ/ngày.
        // An toàn: 1 (tăng = mở pilot nhiều quẻ, sửa spec 03-api C-01 kèm theo).
        'free_per_day' => 1,
    ],
];
