<?php

namespace App\Services;

use App\Domain\Rules;
use App\Domain\Topic;
use App\Models\Device;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * BE-2 — 03-api #7/#7b/#9: stub tạo đơn theo CONTRACT payOS đã chốt (code thật PAY-01
 * sóng 2, §12). 1 trách nhiệm: vòng đời ĐƠN TIỀN. Không đụng AI, không gieo quẻ.
 * Bất biến tiền: trạng thái 1 chiều qua Payment::transitTo (log + exception nếu ngược),
 * idempotency #7: same key same body = same đơn 200, khác body = 409.
 */
class PaymentService
{
    /**
     * #7 — tạo đơn. Trả [payment, createdNew]; controller map 201/200 theo createdNew.
     *
     * @param  array{kind?:string,topic?:string|null,amount_vnd?:int|null,idempotency_key?:string}  $body
     */
    public function create(Device $device, array $body): array
    {
        $kind = (string) ($body['kind'] ?? '');
        $errors = [];
        if (! in_array($kind, ['unlock', 'donate'], true)) {
            $errors['kind'] = ['kind phải là unlock hoặc donate.'];
        }
        $topic = null;
        if ($kind === 'unlock') {
            $t = Topic::tryFrom((string) ($body['topic'] ?? ''));
            if ($t === null) {
                $errors['topic'] = ['topic không thuộc C-02.'];
            } else {
                $topic = $t->value;
            }
        } elseif (! empty($body['topic'])) {
            $errors['topic'] = ['donate không có topic (03-api §7).'];
        }
        $amount = (int) ($body['amount_vnd'] ?? 0);
        if ($kind === 'unlock') {
            // spec §7: client gửi khác → server GHI ĐÈ 29000, không lỗi.
            $amount = Rules::PRICE_UNLOCK_VND;
        } elseif ($kind === 'donate' && ($amount < Rules::DONATE_MIN_VND || $amount > Rules::DONATE_MAX_VND)) {
            $errors['amount_vnd'] = ['Lễ tùy tâm trong khoảng 1.000–500.000đ (C-07).'];
        }
        $key = trim((string) ($body['idempotency_key'] ?? ''));
        if (! preg_match('/^.{8,64}$/', $key)) {
            $errors['idempotency_key'] = ['idempotency_key bắt buộc 8–64 ký tự.'];
        }
        if ($errors !== []) {
            throw PaymentException::validation($errors);
        }

        $hash = hash('sha256', $kind.'|'.($topic ?? '').'|'.$amount);

        $existing = Payment::query()->where('device_id', $device->device_id)
            ->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            if ($existing->request_hash !== $hash) {
                throw PaymentException::conflict();
            }

            return [$existing, false]; // same key same body → trả lại đơn cũ (200)
        }

        // uq_payments_entitlement: mỗi device MỘT row unlock/topic (02-db §6). Bấm mua lại
        // chủ đề đã có đơn (kể cả đã paid) → replay đơn đó, không insert (tránh 500 UQ).
        if ($kind === 'unlock') {
            $prior = Payment::query()->where('device_id', $device->device_id)
                ->where('kind', 'unlock')->where('topic', $topic)->first();
            if ($prior !== null) {
                return [$prior, false];
            }
        }

        try {
            $payment = Payment::query()->create([
                'order_code' => $this->nextOrderCode(),
                'device_id' => $device->device_id,
                'kind' => $kind,
                'topic' => $topic,
                'amount_vnd' => $amount,
                'status' => Payment::ST_PENDING,
                'idempotency_key' => $key,
                'request_hash' => $hash,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // uq_payments_idem là TOÀN CỤC theo key (02-db §6): key đụng đơn device khác
            // → 409 (không replay cross-device, tránh lộ đơn người lạ); retry-safe.
            if (! str_contains($e->getMessage(), 'uq_payments_idem')) {
                throw $e;
            }

            throw PaymentException::conflict();
        }

        return [$payment, true];
    }

    /**
     * #7b — CHỈ local/qa (controller chặn production 404): đánh thức handler webhook
     * nội bộ — paid + paid_at + gateway_ref stub. Repeat-safe (transitTo idempotent).
     */
    public function simulatePaid(int $orderCode): Payment
    {
        $payment = Payment::query()->where('order_code', $orderCode)->first();
        if ($payment === null) {
            throw PaymentException::notFound();
        }
        if ($payment->status === Payment::ST_PAID) {
            return $payment; // repeat-safe (webhook lặp): paid là trạng thái cuối, giữ nguyên paid_at
        }
        $payment->transitTo(Payment::ST_PAID, [
            'paid_at' => now(),
            'gateway_ref' => 'stub_'.Str::random(12),
        ]);
        logger()->info('payments.simulate_paid', ['order_code' => $orderCode, 'to' => $payment->status]);

        return $payment;
    }

    /** #9 — poll trạng thái đơn, ẩn tồn tại đơn của device khác (404 như §6). */
    public function statusFor(Device $device, int $orderCode): Payment
    {
        $payment = Payment::query()
            ->where('order_code', $orderCode)
            ->where('device_id', $device->device_id)
            ->first();
        if ($payment === null) {
            throw PaymentException::notFound();
        }

        return $payment;
    }

    /**
     * #8 (03-api §8) — thân xử lý IPN payOS, TẦM QUỐC TẾ không theo device:
     * chỉ tin 3 field orderCode/amount/transactionRef. Idempotent theo gateway_ref:
     * đơn đã paid → 200 ngay, không sửa gì (kể cả paid_at). cancelled → cancelled;
     * sai số tiền → `expired` + log cảnh báo, vẫn 200 (payOS chốt: không retry-loop).
     * Bất biến tiền: mọi bước qua transitTo (1 chiều + exception nếu ngược).
     */
    public function applyWebhook(int $orderCode, int $amount, string $gatewayRef, bool $cancelled): Payment
    {
        $payment = Payment::query()->where('order_code', $orderCode)->first();
        if ($payment === null) {
            throw PaymentException::notFound();
        }

        if ($payment->status === Payment::ST_PAID) {
            if ((string) $payment->gateway_ref !== $gatewayRef) {
                logger()->warning('payments.webhook.paid_other_ref', [
                    'order_code' => $orderCode,
                    'stored' => $payment->gateway_ref, 'incoming' => $gatewayRef,
                ]);
            }

            return $payment; // webhook lặp → 200 ngay (§8)
        }

        if ($cancelled) {
            $payment->transitTo(Payment::ST_CANCELLED, ['gateway_ref' => $gatewayRef]);
            logger()->info('payments.webhook.cancelled', ['order_code' => $orderCode]);

            return $payment;
        }

        if ($amount !== (int) $payment->amount_vnd) {
            $payment->transitTo(Payment::ST_EXPIRED, ['gateway_ref' => $gatewayRef]);
            logger()->warning('payments.webhook.amount_mismatch', [
                'order_code' => $orderCode, 'expected' => $payment->amount_vnd, 'received' => $amount,
            ]);

            return $payment;
        }

        $payment->transitTo(Payment::ST_PAID, ['paid_at' => now(), 'gateway_ref' => $gatewayRef]);
        logger()->info('payments.webhook.paid', ['order_code' => $orderCode, 'gateway_ref' => $gatewayRef]);

        return $payment;
    }

    /** order_code số tăng dần đụng UQ thì thử lại — payOS dùng số, không uuid. */
    private function nextOrderCode(): int
    {
        for ($i = 0; $i < 5; $i++) {
            $code = random_int(1_000_000_000_0, 9_999_999_999_9);
            if (Payment::query()->where('order_code', $code)->doesntExist()) {
                return $code;
            }
        }

        throw new \RuntimeException('Không sinh được order_code unik (PAY-01 sẽ thay bằng gateway id).');
    }
}
