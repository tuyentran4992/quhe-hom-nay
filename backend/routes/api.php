<?php

use App\Http\Controllers\DrawController;
use App\Http\Controllers\HexagramController;
use App\Http\Controllers\InterpretationController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ShareLinkController;
use App\Http\Controllers\TrackController;
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
    // RL-BE (card t_0e5c0eb9, D1 a3-thuần) — #12 GET /api/draws/{draw_id}/luans:
    // danh sách+bài toàn văn theo QUẺ cho sheet «Đã hỏi quẻ này». Cạnh #4 cùng
    // group device; #4 KHÔNG đổi một dòng. whereNumber chặn id rác trước query.
    Route::get('/draws/{draw_id}/luans', [DrawController::class, 'luans'])
        ->whereNumber('draw_id');                                  // #12 (RL)

    // BE-2 — luận sâu AI (gate 402 + C-03 + C-06, queue DATABASE)
    // REVIEW-LUAN (t_8aa93a01): #5 khóa 1 lượt per (quẻ,topic) → 409 AI_ALREADY_DONE;
    // #5b doc lai bai da luan (nut "Xem lai" cua FE).
    Route::post('/ai/interpretations', [InterpretationController::class, 'store']);
    Route::get('/ai/interpretations/saved', [InterpretationController::class, 'saved']); // #5b
    Route::get('/ai/jobs/{jobUuid}', [InterpretationController::class, 'show']);

    // BE-2 — payments stub contract payOS
    Route::post('/payments/create', [PaymentController::class, 'create']);
    Route::post('/payments/{orderCode}/simulate-paid', [PaymentController::class, 'simulatePaid']);
    Route::get('/payments/{orderCode}/status', [PaymentController::class, 'status']);

    // MKT-F2 — #11 tracking UTM (06-mkt-tracking §3): Throttle 30/phút/IP.
    Route::post('/track', [TrackController::class, 'store']) // #11
        ->middleware('throttle:30,1');

    // F7-BE — share links (SPEC-THE §5): throttle 10/phút/IP — chống sinh token hàng loạt.
    Route::post('/share-links', [ShareLinkController::class, 'store'])
        ->middleware('throttle:10,1');
});

// F7-BE — GET payload công khai KHÔNG cần middleware device (người lạ đọc ảnh thẻ;
// EnsureDeviceSession vẫn đặt attribute qua route group dưới).
Route::get('/share-links/{token}', [ShareLinkController::class, 'show']);

Route::get('/hexagrams/{id}', [HexagramController::class, 'show']) // #2
    ->whereNumber('id');
Route::get('/hexagrams/{id}/hao-texts', [HexagramController::class, 'haoTexts']) // #2b SPEC-3XU
    ->whereNumber('id');
