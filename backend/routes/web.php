<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| [MKT-F2b] t_1bfee292 — GET / = Landing Blade server-render (specs/1.mvp/06-mkt-tracking.md §1).
| Lý do Blade: crawl được, không build; SPA giữ nguyên tại /app/ (routes/spa.php, không đụng).
| UTM pass-through vào CTA href làm SERVER-SIDE (§5.4 verify bằng curl) — sanitize §2:
| chỉ [A-Za-z0-9_\-.:,/() ], cắt 100 ký tự. JS inline dưới (§view) lo POST /api/track (#11).
*/

Route::get('/', function (Request $request) {
    $utm = [];
    foreach (['source', 'medium', 'campaign'] as $k) {
        $raw = mb_substr((string) $request->query('utm_'.$k, ''), 0, 100);
        $clean = trim(preg_replace('/[^A-Za-z0-9_\-.,:()\/ ]/', '', $raw));
        if ($clean !== '') {
            $utm['utm_'.$k] = $clean;
        }
    }

    return view('landing', [
        'utm' => $utm,
        // env nằm sau config/landing.php — test override bằng config([...]).
        'oaUrl' => (string) config('landing.oa_url', ''),
        'ga4Id' => (string) config('landing.ga4_measurement_id', ''),
    ]);
})->name('landing');
