<?php

use App\Http\Controllers\ShareLinkController;
use App\Http\Middleware\EnsureDeviceSession;
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

/*
| F7-BE (t_8c1be286) — trang share card public /s/{token} + OG image + CTA redirect.
| [QA-MERGE t_a24795b4] cơ khí: giữ landing F2 nguyên khối, bỏ route 'welcome' placeholder
| của BE (HEAD đã thay bằng landing), thêm /s/{token} + /cta + /og.png + use statements.
| where alnum để EnsureDeviceSession chỉ chạy cho /s/ hợp lệ hình dạng.
*/
Route::middleware(EnsureDeviceSession::class)->prefix('/s')->group(function () {
    Route::get('/{token}', [ShareLinkController::class, 'showPage'])
        ->where('token', '[A-Za-z0-9]{1,20}');
    Route::get('/{token}/cta', [ShareLinkController::class, 'cta'])
        ->where('token', '[A-Za-z0-9]{1,20}');
    Route::get('/{token}/og.png', [ShareLinkController::class, 'ogPng'])
        ->where('token', '[A-Za-z0-9]{1,20}');
});
