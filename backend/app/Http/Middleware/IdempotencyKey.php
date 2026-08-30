<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * BE-0 placeholder — spec 1.mvp/01 §3; idempotency key 8-64 ký cho POST tiền/AI
 * (03-api §1) — BE-1/PAY-01 implement. CHƯA đăng ký ở bootstrap/app.php.
 */
class IdempotencyKey
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
}
