<?php

use App\Http\Controllers\InterpretationController;
use App\Http\Controllers\PaymentController;
use App\Http\Middleware\EnsureDeviceSession;
use Illuminate\Support\Facades\Route;

/*
| API routes — contract đầy đủ ở specs/1.mvp/03-api.md (11 endpoint #1..#10 + #7b).
| BE-0 chừa đường: health + khung nhóm route; business endpoints thuộc BE-1/BE-2.
| BE-2 (card t_5ee1e352) cung cấp #5 #6 #7 #7b #9 — nhóm middleware EnsureDeviceSession
| (danh tính device = gốc mọi entitlement, 02-db §8). BE-1 nối #1-#4,#10 vào CÙNG nhóm.
*/

Route::get('/health', fn () => response()->json(['data' => ['status' => 'ok']]));

Route::middleware(EnsureDeviceSession::class)->group(function () {
    // #5/#6 — luận sâu AI (gate 402 + C-03 + C-06, queue DATABASE)
    Route::post('/ai/interpretations', [InterpretationController::class, 'store']);
    Route::get('/ai/jobs/{jobUuid}', [InterpretationController::class, 'show']);

    // #7/#7b/#9 — payments stub contract payOS (PAY-01 thay internals sóng 2)
    Route::post('/payments/create', [PaymentController::class, 'create']);
    Route::post('/payments/{orderCode}/simulate-paid', [PaymentController::class, 'simulatePaid']);
    Route::get('/payments/{orderCode}/status', [PaymentController::class, 'status']);
});
