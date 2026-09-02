<?php

use App\Domain\Rules;

/*
| AI-Box provider — spec 1.mvp/01 §2/§5. Key KHÔNG commit (đọc env deploy).
| Gọi CHỈ từ queue worker (app/Jobs/), không gọi đồng bộ trong request HTTP.
*/

return [
    'api_key' => env('AIBOX_API_KEY'),
    'base_url' => env('AIBOX_BASE_URL', 'https://api.example-aibox.test/v1'),
    'model' => env('AIBOX_MODEL', 'aibox-default'),
    // LUAN-V3 §5.2: model router danh mục. BUG-V3-1 (card t_05d92158): mặc định
    // PHẢI non-reasoning (Rules::AI_ROUTER_MODEL) — để rỗng trước đây rơi về
    // model luận (deepseek-v4-flash reasoning) = router chết im lặng 100%.
    'router_model' => env('AIBOX_ROUTER_MODEL', Rules::AI_ROUTER_MODEL),
    // FIX-LUAN-SAU 02/09: ngân sách (giây) cho phép regenerate khi dính filter.
    // Mặc định = Rules::AI_FILTER_REGENERATE_BUDGET_S; test override qua config.
    'filter_regen_budget_s' => (float) env('AIBOX_FILTER_REGEN_BUDGET_S', Rules::AI_FILTER_REGENERATE_BUDGET_S),
];
