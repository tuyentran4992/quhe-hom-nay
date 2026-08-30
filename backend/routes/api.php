<?php

use Illuminate\Support\Facades\Route;

/*
| API routes — contract đầy đủ ở specs/1.mvp/03-api.md (11 endpoint #1..#10 + #7b).
| BE-0 chừa đường: health + khung nhóm route; business endpoints thuộc BE-1/BE-2.
| Middleware EnsureDeviceSession / IdempotencyKey: BE-1 implement (app/Http/Middleware/).
*/

Route::get('/health', fn () => response()->json(['data' => ['status' => 'ok']]));
