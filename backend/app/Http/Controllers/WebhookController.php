<?php

namespace App\Http\Controllers;

use App\Http\ApiError;
use App\Services\PayOsSignatureVerifier;
use App\Services\PaymentException;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * t_a0d9ee0f — 03-api §8: `POST /api/webhooks/payos` (IPN). Route #8 nằm NGOÀI
 * EnsureDeviceSession — gateway không mang cookie device, và webhook lạ không được
 *污染源 devices table. Verify signature TRƯỚC khi parse/động chạm dữ liệu (§8).
 * PAY-01 đổ key thật: chỉ env PAYOS_WEBHOOK_SECRET đổi, code giữ nguyên.
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $service,
        private readonly PayOsSignatureVerifier $verifier,
    ) {
    }

    public function payos(Request $request): JsonResponse
    {
        $raw = (string) $request->getContent();
        if (! $this->verifier->verify($raw, $request->header('X-PayOS-Signature'))) {
            // §0.3: 401 UNAUTHENTICATED "webhook token sai" — log KHÔNG ghi body (chứa mã đơn).
            logger()->warning('webhook.payos.bad_signature', ['ip' => $request->ip()]);

            return ApiError::unauthenticated('Chữ ký webhook không hợp lệ.');
        }

        $payload = json_decode($raw, true);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : null;
        $orderCode = $data['orderCode'] ?? null;
        $amount = $data['amount'] ?? null;
        $ref = $data['transactionRef'] ?? null;
        if ($data === null || ! is_numeric($orderCode) || ! is_numeric($amount) || ! is_string($ref) || $ref === '') {
            return ApiError::json(400, 'BAD_REQUEST', 'Body webhook không đúng cấu trúc payOS §8.');
        }

        try {
            $this->service->applyWebhook((int) $orderCode, (int) $amount, $ref, (bool) ($data['cancelled'] ?? false));
        } catch (PaymentException $e) {
            return $e->toResponse();
        }

        // payOS check đúng chuỗi này — không thêm message/details.
        return response()->json(['error' => ['code' => 'OK']]);
    }
}
