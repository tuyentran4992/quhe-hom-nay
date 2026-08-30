<?php

namespace App\Http\Middleware;

use App\Domain\DeviceIdentity;
use App\Models\Device;
use Closure;
use Illuminate\Http\Request;

/**
 * BE-2 (theo 02-db §8 đã chốt) — gán/nhận device id cho MỌI request /api.
 * Cookie `qhn_device`: HttpOnly, SameSite=Lax, Max-Age 400 ngày, path /.
 * Rule nghiệp vụ khóa theo device_id chứ KHÔNG theo session Laravel (E6: session hết
 * hạn không được mất tiền). BE-1 dùng cùng middleware này cho #1-#4 — file đặt ở
 * BE-2 vì gate 402/cooldown/cap của #5 bắt buộc danh tính device tồn tại.
 * Request đến đây có $request->attributes['device'] là Device đã upsert.
 */
class EnsureDeviceSession
{
    public const COOKIE = 'qhn_device';

    private const MAX_AGE = 34560000; // 400 ngày

    public function handle(Request $request, Closure $next)
    {
        $cookieId = $request->cookies->get(self::COOKIE);
        $isNew = false;

        if (DeviceIdentity::isValid($cookieId)) {
            $device = Device::find($cookieId);
        }
        $device ??= $this->issueNew();
        $isNew = $device->wasRecentlyCreated;

        // last_seen mấp mí mỗi request — rẻ, không cần đồng bộ chính xác từng giây.
        Device::withoutTimestamps(fn () => $device->forceFill(['last_seen' => now()])->save());

        $request->attributes->set('device', $device);

        $response = $next($request);

        if ($isNew) {
            $response->cookie(self::COOKIE, $device->device_id, self::MAX_AGE, '/', null, false, true);
        }

        return $response;
    }

    private function issueNew(): Device
    {
        // đụng PK ngẫu nhiên thì thử lại (xác suất ~0 với 26 ký tự base32)
        for ($i = 0; $i < 3; $i++) {
            $id = DeviceIdentity::generate();
            if (Device::query()->find($id) === null) {
                return Device::forceCreate([
                    'device_id' => $id,
                    'first_seen' => now(),
                    'last_seen' => now(),
                ]);
            }
        }

        throw new \RuntimeException('Không sinh được device_id hợp lệ.');
    }
}
