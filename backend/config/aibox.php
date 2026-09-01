<?php

/*
| AI-Box provider — spec 1.mvp/01 §2/§5. Key KHÔNG commit (đọc env deploy).
| Gọi CHỈ từ queue worker (app/Jobs/), không gọi đồng bộ trong request HTTP.
*/

return [
    'api_key' => env('AIBOX_API_KEY'),
    'base_url' => env('AIBOX_BASE_URL', 'https://api.example-aibox.test/v1'),
    'model' => env('AIBOX_MODEL', 'aibox-default'),
    // LUAN-V3 §5.2: model router danh mục — rỗng → fallback AIBOX_MODEL (cùng base_url/key).
    'router_model' => env('AIBOX_ROUTER_MODEL', ''),
];
