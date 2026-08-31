<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Event;

/**
 * MKT-F2 — specs/1.mvp/06-mkt-tracking.md §2: nghiệp vụ track đặt ở 1 nơi duy nhất:
 * sanitize utm_* (≤100 ký tự, charset khóa khử injection) + bất biến FIRST-TOUCH
 * (chỉ ghi devices.utm_* khi cột đang NULL, sự kiện sau không đè) + ghi row events.
 * 1 service 1 trách nhiệm: không luận giải, không đụng tiền, không đọc request.
 */
class TrackService
{
    /** §2: cắt gọn 100 ký tự, chỉ [A-Za-z0-9_\-.,:()/ ] — mọi ký tự khác bị khử. */
    public const UTM_MAX_LENGTH = 100;

    public const UTM_ALLOWED_CHARS = '/[^A-Za-z0-9_\-.,:()\/ ]/u';

    /** §3: props JSON ≤ 2KB — serialize lại nếu to quá thì không lưu (event vẫn ghi). */
    private const PROPS_MAX_BYTES = 2048;

    private const UTM_COLUMNS = ['utm_source', 'utm_medium', 'utm_campaign'];

    /**
     * @param  array{source?:string|null, medium?:string|null, campaign?:string|null}  $utm
     * @param  array<string, mixed>|null  $props
     */
    public function track(Device $device, string $name, array $utm = [], ?array $props = null): Event
    {
        $event = Event::query()->create([
            'device_id' => $device->device_id,
            'name' => $name,
            'props' => $this->normalizeProps($props),
        ]);

        $this->applyFirstTouch($device, $utm);

        return $event;
    }

    /**
     * FIRST-TOUCH (bất biến): chỉ ghi khi cột ĐANG NULL — thiết bị giữ campaign
     * đầu tiên mãi mãi; sự kiện sau không đè. Update có điều kiện NULL trong WHERE
     * để race 2 request cùng lúc vẫn tôn trọng first-touch ở tầng câu lệnh.
     *
     * @param  array<string, string|null>  $utm  key source|medium|campaign
     */
    private function applyFirstTouch(Device $device, array $utm): void
    {
        foreach ($utm as $key => $value) {
            $column = 'utm_' . $key;
            if (! in_array($column, self::UTM_COLUMNS, true)) {
                continue;
            }
            $clean = $this->sanitize($value);
            if ($clean === null || $clean === '') {
                continue;
            }
            // cột đã có giá trị → không đè (first-touch); null → điền
            $affected = Device::query()
                ->where('device_id', $device->device_id)
                ->whereNull($column)
                ->update([$column => $clean]);
            if ($affected === 1) {
                $device->{$column} = $clean;
            }
        }
    }

    /** Strip ký tự ngoài charset khóa rồi cắt 100 (strip trước để không phí chỗ cho rác). */
    public function sanitize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        // preg_replace với /u fail trên binary rác → fallback không unicode
        $clean = preg_replace(self::UTM_ALLOWED_CHARS, '', $value)
            ?? preg_replace('/[^A-Za-z0-9_\-.,:()\/ ]/', '', $value);

        return mb_substr((string) $clean, 0, self::UTM_MAX_LENGTH);
    }

    /** @param  array<string, mixed>|null  $props */
    private function normalizeProps(?array $props): ?array
    {
        if ($props === null || $props === []) {
            return null;
        }
        if (strlen((string) json_encode($props)) > self::PROPS_MAX_BYTES) {
            return null; // §3: quá 2KB → serialize lại = bỏ, event vẫn ghi
        }

        return $props;
    }
}
