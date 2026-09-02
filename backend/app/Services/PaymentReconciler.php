<?php

namespace App\Services;

use App\Models\Payment;

/**
 * BE-PAY-EXPIRE (t_bbfff19b — QA-DONATE-QR ghi nhận #6) — reconcile sổ sách:
 * đơn `pending` chết quá TTL (C-14 config/project.php, cấm hardcode) → `expired`.
 * FE đã tự expire UI-side sau 300s nhưng DB không bao giờ đổi → pending cô đơn
 * tích tụ. Tách khỏi PaymentService (vòng đời đơn qua API #7/#8/#9) vì đây là
 * đường ghi CRON, không phải request — 1 class 1 trách nhiệm, test độc lập.
 *
 * An toàn race: từng row đổi bằng UPDATE có điều kiện `status='pending'` đọc
 * LẠI tại thời điểm ghi — webhook/simulate paid chen giữa hai bước = 0 rows,
 * đơn paid giây cuối không bao giờ bị nuốt (AC-2 race case). Cờ C-15 tắt →
 * no-op. Idempotent: row đã expired không được chạm (WHERE loại dần).
 *
 * PAY-01 (gateway thật): TRƯỚC khi expire từng batch phải query trạng thái
 * thật phía payOS (`GET /v4/payments/{orderCode}`) cho các đơn có gateway
 * ref — stub hiện không có API này, row payments là nguồn duy nhất; đừng quên
 * hook đó khi thay internals, nếu không sẽ expire oan đơn đã trả.
 */
class PaymentReconciler
{
    /** @return array{expired:int, scanned:int} số liệu cho command + test */
    public function expireStalePending(?\Carbon\CarbonInterface $now = null): array
    {
        if (! config('project.pay.expire_cron_enabled')) {
            return ['expired' => 0, 'scanned' => 0];
        }
        $ttl = max(1, (int) config('project.pay.expire_ttl_seconds'));
        $cutoff = ($now ?? now())->subSeconds($ttl);

        $expired = 0;
        $scanned = 0;
        // cursor theo id: không reload tập kết quả trong lúc ghi (batch an toàn).
        $lastId = 0;
        do {
            $ids = Payment::query()
                ->where('status', Payment::ST_PENDING)
                ->where('created_at', '<', $cutoff)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(200)
                ->pluck('id');
            foreach ($ids as $id) {
                $lastId = max($lastId, (int) $id);
                $scanned++;
                // atomic — điều kiện status='pending' at-WRITE-time là chốt race.
                $won = Payment::query()
                    ->where('id', $id)
                    ->where('status', Payment::ST_PENDING)
                    ->update(['status' => Payment::ST_EXPIRED, 'updated_at' => now()]);
                if ($won === 1) {
                    $expired++;
                    logger()->info('payments.cron.expired', ['payment_id' => $id, 'ttl_s' => $ttl]);
                }
            }
        } while ($ids->count() === 200);

        return ['expired' => $expired, 'scanned' => $scanned];
    }
}
