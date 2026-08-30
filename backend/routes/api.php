<?php

use App\Http\Controllers\DrawController;
use App\Http\Controllers\HexagramController;
use App\Http\Controllers\InterpretationController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\EnsureDeviceSession;
use Illuminate\Support\Facades\Route;

/*
| API routes — contract đầy đủ ở specs/1.mvp/03-api.md (11 endpoint #1..#10 + #7b).
| QA SMOKE MERGE (qa-engineer t_5cd31bb9, không production): gộp BE-1 (#1-#4,#10,#2)
| + BE-2 (#5 #6 #7 #7b #9) để chạy E2E — dev-lead là người merge main chính thức.
*/

Route::get('/health', fn () => response()->json(['data' => ['status' => 'ok']]));

// t_a0d9ee0f — #8 IPN payOS (03-api §8): NGOÀI EnsureDeviceSession — gateway không
// mang cookie device; webhook lạ không được làm nhiễm devices table. Verifier 401 trước parse.
Route::post('/webhooks/payos', [WebhookController::class, 'payos']); // #8

Route::middleware(EnsureDeviceSession::class)->group(function () {
    // BE-1 — gieo quẻ/hiện tại
    Route::get('/me', [MeController::class, 'index']);            // #1
    Route::get('/me/today', [MeController::class, 'today']);      // #10
    Route::post('/draws', [DrawController::class, 'store']);      // #3 (C-01)
    Route::get('/draws/history', [DrawController::class, 'history']); // #4

    // BE-2 — luận sâu AI (gate 402 + C-03 + C-06, queue DATABASE)
    Route::post('/ai/interpretations', [InterpretationController::class, 'store']);
    Route::get('/ai/jobs/{jobUuid}', [InterpretationController::class, 'show']);

    // BE-2 — payments stub contract payOS
    Route::post('/payments/create', [PaymentController::class, 'create']);
    Route::post('/payments/{orderCode}/simulate-paid', [PaymentController::class, 'simulatePaid']);
    Route::get('/payments/{orderCode}/status', [PaymentController::class, 'status']);
});

Route::get('/hexagrams/{id}', [HexagramController::class, 'show']) // #2
    ->whereNumber('id');
