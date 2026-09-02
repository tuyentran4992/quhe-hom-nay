<?php

/*
| AI-Box provider — spec 1.mvp/01 §2/§5. Key KHÔNG commit (đọc env deploy).
| Gọi CHỈ từ queue worker (app/Jobs/), không gọi đồng bộ trong request HTTP.
|
| CFG-BE (t_ce2a6834): file này CHỈ còn hạ tầng kết nối (key/url/model provider).
| Mọi SỐ/CỜ nghiệp vụ AI (attempts, timeout, cooldown, cap, regen, router budget,
| lock_one_luan) đã dời về config/project.php. Env AIBOX_* chỉ còn là override
| hạ tầng; value rỗng → reader dùng default project.php.
*/

return [
    'api_key' => env('AIBOX_API_KEY'),
    'base_url' => env('AIBOX_BASE_URL', 'https://api.example-aibox.test/v1'),
    'model' => env('AIBOX_MODEL', 'aibox-default'),
    // LUAN-V3 §5.2 + BUG-V3-1 (card t_05d92158): override model router danh mục.
    // Rỗng (mặc định) → AiBoxClient đọc project.php ai.router_model (bắt buộc
    // non-reasoning) — để env mặc định thẳng model luận reasoning = router chết
    // im lặng 100%, nên default nghiệp vụ nằm 1 nguồn project.php, không ở đây.
    'router_model' => env('AIBOX_ROUTER_MODEL', ''),
    // FIX-LUAN-SAU 02/09: override (giây) cho phép regenerate khi dính filter.
    // null (mặc định) → reader dùng project.php ai.filter_regenerate_budget_s;
    // test override trực tiếp key này qua config().
    'filter_regen_budget_s' => env('AIBOX_FILTER_REGEN_BUDGET_S') !== null
        ? (float) env('AIBOX_FILTER_REGEN_BUDGET_S')
        : null,
];
