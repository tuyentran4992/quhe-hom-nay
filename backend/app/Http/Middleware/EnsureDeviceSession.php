<?php

namespace App\Http\Middleware;

use App\Services\DeviceIdentityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cấp/nhận device id (02-db §8) — chạy TRƯỚC mọi route /api (trừ health):
 * cookie `qhn_device` hợp lệ → attach Device vào request; không có cookie / cookie lạ
 * (device đã xóa DB) → sinh device MỚI + Set-Cookie HttpOnly/SameSite=Lax/Max-Age 400 ngày.
 * Rule nghiệp vụ khóa device_id, không phụ thuộc laravel_session (E6 05-testplan).
 */
class EnsureDeviceSession
{
    public function __construct(private readonly DeviceIdentityService $identity)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        ['device' => $device, 'is_new' => $isNew] = $this->identity->resolve(
            $request->cookies->get(DeviceIdentityService::COOKIE)
        );

        $request->attributes->set('device', $device);
        $request->attributes->set('device_is_new', $isNew);

        $response = $next($request);

        // Chỉ Set-Cookie khi vừa sinh device — FE không cần đọc, chỉ giữ phiên.
        if ($isNew) {
            $response->headers->setCookie(
                cookie(
                    DeviceIdentityService::COOKIE,
                    $device->device_id,
                    DeviceIdentityService::COOKIE_MINUTES,
                    '/',
                    null,
                    false, // local http — production bật secure qua env deploy
                    true,  // HttpOnly — FE không đọc được, đúng §0 03-api
                    false,
                    'lax'
                )
            );
        }

        return $response;
    }
}
