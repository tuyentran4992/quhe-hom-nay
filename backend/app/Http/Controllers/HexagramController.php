<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Hexagram;
use Illuminate\Http\JsonResponse;

/**
 * #2 GET /api/hexagrams/{id} (03-api) — tra cứu quẻ, ánh xạ 1-1 cột bảng hexagrams.
 * `?fields=` chấp nhận và BỎ QUA (trả full — §2). id ngoài 1..64 hoặc chưa seed → 404 NOT_FOUND.
 */
class HexagramController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $hexagram = Hexagram::query()->find($id);

        if ($hexagram === null) {
            throw ApiException::notFound();
        }

        return response()->json(['data' => $hexagram->toApi()]);
    }
}
