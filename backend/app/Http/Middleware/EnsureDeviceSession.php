<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * BE-0 placeholder — spec 1.mvp/01 §3; cấp/nhận device id (03-api §1) — BE-1 implement.
 * CHƯA đăng ký ở bootstrap/app.php: middleware rỗng pass-through, bật khi có logic thật.
 */
class EnsureDeviceSession
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
}
