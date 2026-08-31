<?php

namespace App\Http\Controllers;

use App\Domain\Luan;
use App\Models\Device;
use App\Services\DrawService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #3 POST /api/draws + #4 GET /api/draws/history (03-api).
 * Mỏng — validation ranh giới ở đây, nghiệp vụ C-01 ở DrawService + uq DB.
 * SPEC-3XU: #3 thêm `data.hao_texts` (luật luận §4bis, nguồn hexagram_hao_texts);
 * quẻ biến đã lưu DB nội bộ nhưng KHÔNG xuất hiện ở payload.
 */
class DrawController extends Controller
{
    public function __construct(
        private readonly DrawService $draws,
        private readonly Luan $luan,
    ) {
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
        $draw = $result['draw'];

        // SPEC-3XU F9: data.hao_texts = k phần tử theo hào ĐỘNG của draw này
        // (03-api §3 — FE render trực tiếp, không tự tra; [] khi 0 hào động).
        // Nguồn qua Domain\Luan (1 trách nhiệm tra-luật, controller giữ mỏng).
        $haoTexts = $this->luan->haoTextsForDraw($result['draw']);

        return response()->json(['data' => [
            'draw' => $draw->toApi(),
            'hexagram' => $result['hexagram']->toApi(),
            'hao_texts' => $haoTexts,
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

        $draws = $this->draws->history($device, $limit);

        // SPEC-3XU: #4 giữ format draw[] cũ + thêm `hao_texts` theo từng draw
        // (luật §4bis — chỉ hào ĐỘNG; FE không phải gọi #2b cho mỗi dòng).
        // Tra batch 1 query cho MỌI quẻ trong trang (mapForHexagrams — chống N+1).
        $map = $this->luan->mapForHexagrams($draws->pluck('hexagram_id')->unique()->all());
        $payload = $draws->map(static function ($d) use ($map) {
            $changing = array_map(intval(...), $d->changing_lines ?? []);
            $texts = array_values(array_filter(
                $map[(int) $d->hexagram_id] ?? [],
                static fn (array $t): bool => in_array((int) $t['vi'], $changing, true)
            ));

            return $d->toApi() + ['hao_texts' => $texts];
        })->all();

        return response()->json([
            'data' => $payload,
            'meta' => ['count' => count($payload)],
        ]);
    }
}
