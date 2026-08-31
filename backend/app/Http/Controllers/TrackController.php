<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\Event;
use App\Services\TrackService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * #11 POST /api/track — specs/1.mvp/06-mkt-tracking.md §3 (contract KHÓA).
 * Mỏng: chỉ validate ranh giới + đọc device; sanitize + first-touch nằm
 * TrackService (1 nơi duy nhất, §2). Trả 204 kể cả utm thiếu — vẫn ghi event.
 */
class TrackController extends Controller
{
    private const UTM_KEYS = ['source', 'medium', 'campaign'];

    public function __construct(private readonly TrackService $track)
    {
    }

    public function store(Request $request): Response
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
        ]);

        // whitelist §2 — 1 nguồn sự thật Event::NAME_WHITELIST; ngoài → 422 envelope §0.3
        if (! in_array($data['name'], Event::NAME_WHITELIST, true)) {
            throw ApiException::validationFailed([
                'name' => ['Giá trị của name không nằm trong danh sách cho phép.'],
            ]);
        }

        $utm = $request->input('utm', []);
        if (! is_array($utm)) {
            throw ApiException::validationFailed(['utm' => ['utm phải là object.']]);
        }
        // §3: utm.* optional string ≤100 — SAI KIỂU reject; rác ký tự/dài quá KHÔNG
        // reject, TrackService cắt+sanitize (§2) → không bao giờ 500.
        $errors = [];
        foreach (self::UTM_KEYS as $key) {
            $value = $utm[$key] ?? null;
            if ($value !== null && ! is_string($value)) {
                $errors["utm.$key"] = ['Chuỗi ký tự tối đa 100.'];
            }
        }
        if ($errors !== []) {
            throw ApiException::validationFailed($errors);
        }

        $props = $request->input('props');
        if ($props !== null && ! is_array($props)) {
            throw ApiException::validationFailed(['props' => ['props phải là object.']]);
        }

        $this->track->track(
            $device,
            $data['name'],
            array_intersect_key($utm, array_flip(self::UTM_KEYS)),
            $props,
        );

        return response()->noContent();
    }
}
