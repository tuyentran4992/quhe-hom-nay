<?php

/*
| [MKT-F2b] t_1bfee292 — config đọc env cho landing (06-mkt-tracking §1).
| Tách qua config để Blade/test override được mà không gọi env() trong view.
*/

return [
    // Link OA/Zalo (khuôn kênh C) — rỗng → landing render fallback '#'.
    'oa_url' => env('LANDING_OA_URL', ''),

    // GA4 Measurement ID (G-XXXX) — rỗng → KHÔNG render snippet gtag.
    'ga4_measurement_id' => env('GA4_MEASUREMENT_ID', ''),
];
