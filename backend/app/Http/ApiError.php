<?php

namespace App\Http;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;

/**
 * BE-2 — envelope lỗi duy nhất 03-api.md §0.3:
 * {"error":{"code","message","details"}} — mọi endpoint lỗi trả đúng hình này.
 */
final class ApiError
{
    /** @param array<string,mixed>|Arrayable<string,mixed>|null $details */
    public static function json(
        int $status,
        string $code,
        string $message,
        array|Arrayable|null $details = null,
    ): JsonResponse {
        $details = $details instanceof Arrayable ? $details->toArray() : $details;

        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }

    public static function unauthenticated(string $message = 'Phiên không hợp lệ.'): JsonResponse
    {
        return self::json(401, 'UNAUTHENTICATED', $message);
    }

    public static function notFound(string $message = 'Không tìm thấy dữ liệu.'): JsonResponse
    {
        return self::json(404, 'NOT_FOUND', $message);
    }

    private function __construct()
    {
    }
}
