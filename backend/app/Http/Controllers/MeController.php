<?php

namespace App\Http\Controllers;

use App\Domain\Rules;
use App\Models\Device;
use App\Services\DrawService;
use App\Services\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #1 GET /api/me + #10 GET /api/me/today (03-api) — bootstrap phiên device + quẻ hôm nay.
 * Mỏng: mọi logic ngày/draw nằm DrawService (1 class 1 trách nhiệm).
 */
class MeController extends Controller
{
    public function __construct(
        private readonly DrawService $draws,
        private readonly QuotaService $quota, // QUOTA-N/Q2 — đếm lượt THAT theo draw
    ) {}

    /**
     * QUOTA-N/Q2 (card t_1b5a0c23): "còn x/N" cho FE — max(0, N − lượt THẬT của
     * draw HOM NAY) (card: theo draw hom nay, khong phai moi draw trong qua kh).
     * Chưa gieo quẻ hôm nay → còn nguyên N (quota gan DRAW, quẻ mới = sạch).
     */
    private function remainingDeepReads(Device $device): int
    {
        $today = $this->draws->todayDraw($device);

        return $today === null ? $this->quota->maxPerDraw() : $this->quota->remaining((int) $today->id);
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
            'free_deep' => (bool) config('project.free_deep_preview'),
            'remaining_deep_reads' => $this->remainingDeepReads($device), // QUOTA-N/Q2
            // Q6 (card t_091a0424): N cho chip "Còn x/N" — cùng nguồn QuotaService,
            // FE zero-touch (DetailView quotaMax đã đọc dự phòng field này).
            'max_deep_reads_per_draw' => $this->quota->maxPerDraw(),
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
            'free_deep' => (bool) config('project.free_deep_preview'), // F8-BE C1 — cùng nguồn #1
            'remaining_deep_reads' => $this->remainingDeepReads($device), // QUOTA-N/Q2
            'max_deep_reads_per_draw' => $this->quota->maxPerDraw(), // Q6 card t_091a0424 — cùng nguồn #1
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
        // PREVIEW OVERRIDE (project.php free_deep_preview): mở luận sâu free → FE
        // coi như đã unlock cả 3 topic để vào thẳng vùng hỏi, không đẩy sang paywall.
        if (config('project.free_deep_preview')) {
            return Rules::TOPICS;
        }
        $paid = Schema::hasTable('payments')
            ? DB::table('payments')
                ->where('device_id', $device->device_id)
                ->where('kind', 'unlock')
                ->where('status', 'paid')
                ->pluck('topic')
                ->all()
            : [];

        return array_values(array_unique($paid));
    }
}
