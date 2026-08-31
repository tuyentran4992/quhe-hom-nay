<?php

use App\Http\Controllers\ShareLinkController;
use App\Http\Middleware\EnsureDeviceSession;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
| F7-BE (t_8c1be286) — trang share card public /s/{token} + OG image + CTA redirect.
| LƯU Ý lane (F7-CONTRACT §1): VIEW Blade `share`/`share-404` là của fe-dev
| (card/t_2e969791) — BE sở hữu controller + data + counting; web.php dùng chung,
| dev-lead merge tay giữa lane landing F2 và lane share F7. Route đăng có chủ ý
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
