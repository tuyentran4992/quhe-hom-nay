<?php

use App\Http\Controllers\DrawController;
use App\Http\Controllers\HexagramController;
use App\Http\Controllers\MeController;
use App\Http\Middleware\EnsureDeviceSession;
use Illuminate\Support\Facades\Route;

/*
| API routes — contract đầy đủ ở specs/1.mvp/03-api.md (11 endpoint #1..#10 + #7b).
| BE-1 (card t_7e2b7031): nhóm "gieo quẻ/hiện tại" #1..#4, #10 + middleware device.
| BE-2: #5 #6 #7 #7b #8 #9 (AI + payments) — chừa đường ở file này khi merge.
*/

Route::get('/health', fn () => response()->json(['data' => ['status' => 'ok']]));

Route::middleware(EnsureDeviceSession::class)->group(function () {
    Route::get('/me', [MeController::class, 'index']);            // #1
    Route::get('/me/today', [MeController::class, 'today']);      // #10
    Route::post('/draws', [DrawController::class, 'store']);      // #3 (C-01)
    Route::get('/draws/history', [DrawController::class, 'history']); // #4
});

Route::get('/hexagrams/{id}', [HexagramController::class, 'show'])  // #2 (đọc danh mục, không cần device)
    ->whereNumber('id');
