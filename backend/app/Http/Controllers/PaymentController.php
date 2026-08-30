<?php

namespace App\Http\Controllers;

use App\Domain\Calendar;
use App\Models\Device;
use App\Services\PaymentException;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BE-2 — 03-api #7 (POST /api/payments/create), #7b (simulate-paid, chỉ local/qa),
 * #9 (GET /api/payments/{order_code}/status). Stub theo CONTRACT payOS đã chốt —
 * PAY-01 thay internals, FE không đổi gì (§7 ghi rõ shape GIỐNG HẸT bản thật).
 */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $service)
    {
    }

    /** #7 — 201 đơn mới | 200 replay same key same body; 409 conflict; 422 validate. */
    public function create(Request $request): JsonResponse
    {
        /** @var Device */
        $device = $request->attributes->get('device');

        try {
            [$payment, $new] = $this->service->create($device, (array) $request->json()->all());
        } catch (PaymentException $e) {
            return $e->toResponse();
        }

        return response()->json(['data' => $this->qrPayload($payment)], $new ? 201 : 200);
    }

    /**
     * #7b — CHỈ env local/qa: production phải 404 như đơn ma (03-api §7b).
     * Không validate signature gì — stub đánh thức handler paid nội bộ.
     */
    public function simulatePaid(Request $request, string $orderCode): JsonResponse
    {
        if (app()->environment('production')) {
            return PaymentException::notFound()->toResponse();
        }
        if (filter_var($orderCode, FILTER_VALIDATE_INT) === false) {
            return PaymentException::notFound()->toResponse();
        }

        try {
            $payment = $this->service->simulatePaid((int) $orderCode);
        } catch (PaymentException $e) {
            return $e->toResponse();
        }

        return response()->json(['data' => $payment->toStatusApi()]);
    }

    /** #9 — FE poll sau QR. Đơn của device khác = 404 (ẩn tồn tại). */
    public function status(Request $request, string $orderCode): JsonResponse
    {
        /** @var Device */
        $device = $request->attributes->get('device');
        if (filter_var($orderCode, FILTER_VALIDATE_INT) === false) {
            return PaymentException::notFound()->toResponse();
        }

        try {
            $payment = $this->service->statusFor($device, (int) $orderCode);
        } catch (PaymentException $e) {
            return $e->toResponse();
        }

        return response()->json(['data' => $payment->toStatusApi()]);
    }

    /** body data #7 — đúng 9 field contract §7 (stub=true, confirm_url trỏ #7b). */
    private function qrPayload(\App\Models\Payment $p): array
    {
        return [
            'order_code' => (int) $p->order_code,
            'kind' => $p->kind,
            'topic' => $p->topic,
            'amount_vnd' => (int) $p->amount_vnd,
            'status' => $p->status,
            'qr_data' => 'vietqr/action/qr/970436/stub'.$p->order_code.'/'.$p->amount_vnd.'/Qu+Hom+Nay',
            'confirm_url' => url('/api/payments/'.$p->order_code.'/simulate-paid'),
            'checkout_url' => '/pay/'.$p->order_code,
            'stub' => true,
            'expires_at' => Calendar::nextMidnightVnRfc3339(), // stub: hết ngày VN (PAY-01 đổi 15p)
        ];
    }
}
