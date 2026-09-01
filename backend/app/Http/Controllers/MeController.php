<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\DrawService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #1 GET /api/me + #10 GET /api/me/today (03-api) — bootstrap phiên device + quẻ hôm nay.
 * Mỏng: mọi logic ngày/draw nằm DrawService (1 class 1 trách nhiệm).
 */
class MeController extends Controller
{
    public function __construct(private readonly DrawService $draws)
    {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $today = $this->draws->todayDraw($device);

        return response()->json([
            'device_id' => $device->device_id, // chỉ debug; FE không cần dùng (§1)
            'is_new_device' => (bool) $request->attributes->get('device_is_new'),
            'today_draw' => $today?->toApi(),
            'entitlements' => $this->entitlements($device),
            'server_date_vn' => $this->draws->serverDateVn(now()),
            // F8-BE (C1): tín hiệu "luận sâu đang FREE" — FE CHỈ tin key này,
            // không suy từ entitlements (device trả 29k cũng đủ 3 topic).
            'free_deep' => (bool) config('preview.free_deep'),
        ]);
    }

    /** #10 alias đọc nhanh — 3 field cuối của §1, cùng Service, không tải hexagram. */
    public function today(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        return response()->json(['data' => [
            'today_draw' => $this->draws->todayDraw($device)?->toApi(),
            'entitlements' => $this->entitlements($device),
            'server_date_vn' => $this->draws->serverDateVn(now()),
            'free_deep' => (bool) config('preview.free_deep'), // F8-BE C1 — cùng nguồn #1
        ]]);
    }

    /**
     * Topic đã unlock = payments kind=unlock status=paid (02-db §6).
     * BE-2/PAY-01 sở hữu bảng payments; BE-1 chỉ đọc — bảng chưa có (migration BE-2 chưa
     * merge) → rỗng, ĐÚNG với device chưa trả phí. Không import service BE-2 (tránh đảo phụ thuộc).
     *
     * @return string[] ⊆ Rules::TOPICS
     */
    private function entitlements(Device $device): array
    {
        // PREVIEW FLAG (không tồn tại ở main): mở luận sâu free → FE coi như đã unlock
        // cả 3 topic để vào thẳng vùng hỏi, không đẩy sang màn paywall.
        if (config('preview.free_deep')) {
            return \App\Domain\Rules::TOPICS;
        }
        $paid = \Illuminate\Support\Facades\Schema::hasTable('payments')
            ? \Illuminate\Support\Facades\DB::table('payments')
                ->where('device_id', $device->device_id)
                ->where('kind', 'unlock')
                ->where('status', 'paid')
                ->pluck('topic')
                ->all()
            : [];

        return array_values(array_unique($paid));
    }
}
