<?php

namespace App\Http\Controllers;

use App\Domain\Luan;
use App\Exceptions\ApiException;
use App\Models\Hexagram;
use Illuminate\Http\JsonResponse;

/**
 * #2 GET /api/hexagrams/{id} (03-api) — tra cứu quẻ, ánh xạ 1-1 cột bảng hexagrams.
 * `?fields=` chấp nhận và BỎ QUA (trả full — §2). id ngoài 1..64 hoặc chưa seed → 404 NOT_FOUND.
 * #2b GET /api/hexagrams/{id}/hao-texts (SPEC-3XU 03-api §2b): 6 từ hào vi=1..6,
 * nguồn bảng hexagram_hao_texts — FE lọc theo changing_lines khi S3/deep-link.
 */
class HexagramController extends Controller
{
    public function __construct(private readonly Luan $luan = new Luan())
    {
    }

    public function show(int $id): JsonResponse
    {
        $hexagram = Hexagram::query()->find($id);

        if ($hexagram === null) {
            throw ApiException::notFound();
        }

        return response()->json(['data' => $hexagram->toApi()]);
    }

    /** #2b — luôn đủ 6 phần tử thứ tự sơ→thượng; 404 nếu id ngoài 1..64/chưa seed. */
    public function haoTexts(int $id): JsonResponse
    {
        $texts = $this->luan->mapForHexagrams([$id])[$id] ?? [];

        if (count($texts) !== 6) {
            throw ApiException::notFound();
        }

        return response()->json(['data' => [
            'hexagram_id' => $id,
            'hao' => $texts,
        ]]);
    }
}
