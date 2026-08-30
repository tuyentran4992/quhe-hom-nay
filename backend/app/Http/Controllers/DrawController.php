<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\DrawService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #3 POST /api/draws + #4 GET /api/draws/history (03-api).
 * Mỏng — validation ranh giới ở đây, nghiệp vụ C-01 ở DrawService + uq DB.
 */
class DrawController extends Controller
{
    public function __construct(private readonly DrawService $draws)
    {
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        // §3: body {} hợp lệ; client_date_vn optional CHỈ để log đối chiếu —
        // server KHÔNG dùng tính toán. Sai format cũng không reject (spec chỉ bắt khi có).
        $data = $request->validate([
            'client_date_vn' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);

        if (! empty($data['client_date_vn'])) {
            logger()->info('draw.client_date_vn', [
                'device' => $device->device_id,
                'client_date_vn' => $data['client_date_vn'],
                'server_date_vn' => $this->draws->serverDateVn(now()),
            ]);
        }

        $result = $this->draws->drawFor($device, now());

        return response()->json(['data' => [
            'draw' => $result['draw']->toApi(),
            'hexagram' => $result['hexagram']->toApi(),
            'already_drawn' => false, // luôn false ở 201 (§3)
        ]], 201);
    }

    public function history(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        // §4: limit int 1..50 default 20, ngoài khoảng → 422 VALIDATION_FAILED envelope §0.3
        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $limit = (int) ($validated['limit'] ?? 20);

        $draws = $this->draws->history($device, $limit)->map(fn ($d) => $d->toApi())->all();

        return response()->json([
            'data' => $draws,
            'meta' => ['count' => count($draws)],
        ]);
    }
}
