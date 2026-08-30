<?php

/*
| payOS — sóng 2 (contract 03-api #7/#7b/#8 đã chốt; code thật thuộc PAY-01).
| BE-0 chỉ chừa cấu hình + placeholder rỗng theo spec 1.mvp/01 §5.
*/

return [
    'client_id' => env('PAYOS_CLIENT_ID'),
    'api_key' => env('PAYOS_API_KEY'),
    'checksum_key' => env('PAYOS_CHECKSUM_KEY'),
    'webhook_secret' => env('PAYOS_WEBHOOK_SECRET'),
];
