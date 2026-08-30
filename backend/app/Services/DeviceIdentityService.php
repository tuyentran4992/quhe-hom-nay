<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Device identity — 02-db §8 (BẤT BIẾN kiến trúc):
 * không cookie `qhn_device` → sinh device_id 26 ký tự base32 CSPRNG → INSERT devices
 * → middleware gắn cookie. Mọi rule khóa theo device_id, KHÔNG theo session Laravel.
 * 1 service = 1 trách nhiệm: chỉ định danh, không gieo quẻ, không đụng tiền.
 */
class DeviceIdentityService
{
    public const COOKIE = 'qhn_device';

    public const COOKIE_MINUTES = 576000; // 400 ngày

    /** Alphabet base32 RFC4648 (A-Z + 2-7). */
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Cookie hợp lệ = thiết bị CÓ MẶT trong DB (02-db §8 — rule khóa theo device_id).
     * Format [A-Z2-7]{26} chỉ là luật SINH random, không phải validator cookie vào:
     * cookie lạ/thiết bị đã xóa row → coi như mới, sinh device khác (E6 05-testplan).
     *
     * @return array{device: Device, is_new: bool}
     */
    public function resolve(?string $cookieValue): array
    {
        if (is_string($cookieValue) && $cookieValue !== '' && strlen($cookieValue) <= 26) {
            $device = Device::query()->find($cookieValue);
            if ($device !== null) {
                // last_seen refresh (02-db §2); rule nghiệp vụ không đọc cột này
                DB::table('devices')->where('device_id', $cookieValue)
                    ->update(['last_seen' => now()]);
                $device->last_seen = now();

                return ['device' => $device, 'is_new' => false];
            }
        }

        return ['device' => $this->createDevice(), 'is_new' => true];
    }

    private function createDevice(): Device
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $id = $this->newDeviceId();
            try {
                return Device::query()->create(['device_id' => $id]);
            } catch (QueryException $e) {
                // Va chạm PK CSPRNG về thực tế = 0; retry nếu row đã có, lỗi khác → fail loud.
                if (! Device::query()->whereKey($id)->exists()) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Không sinh được device_id hợp lệ sau 3 lần thử.');
    }

    private function newDeviceId(): string
    {
        // Mỗi byte random → 1 ký tự alphabet (đủ đều vì 256 % 32 == 0) → cần đúng 26 byte
        $alphabet = self::ALPHABET;
        $out = '';
        foreach (array_values(unpack('C*', random_bytes(26))) as $byte) {
            $out .= $alphabet[$byte % 32];
        }

        return $out;
    }
}
