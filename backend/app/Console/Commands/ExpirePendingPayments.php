<?php

namespace App\Console\Commands;

use App\Services\PaymentReconciler;
use Illuminate\Console\Command;

/**
 * BE-PAY-EXPIRE (t_bbfff19b — QA-DONATE-QR ghi nhận #6) — quét đơn `pending` quá
 * TTL và transit `expired` phía DB. Toàn bộ luật (ngưỡng C-14, cờ C-15,
 * race-safe, hook PAY-01 query gateway) nằm trong PaymentReconciler::
 * expireStalePending() — class này CHỈ là adapter: chạy lệnh, in số liệu.
 *
 * Hạ tầng hiện chưa có crond trên server (01 §2, giống ghi chú SweepZombieJobs):
 * schedule khai báo ở routes/console.php và chỉ nổ lực khi ops gắn
 * `* * * * * php artisan schedule:run`. Lệnh idempotent → chạy chồng vô hại.
 * Chạy tay khi cần: php artisan payments:expire-pending
 */
class ExpirePendingPayments extends Command
{
    protected $signature = 'payments:expire-pending';

    protected $description = 'Transit expired cho đơn pending quá TTL (config project.pay) — chống row cô đơn';

    public function handle(PaymentReconciler $reconciler): int
    {
        $r = $reconciler->expireStalePending();

        $this->info("expire-pending: done: expired={$r['expired']} scanned={$r['scanned']}");

        return self::SUCCESS;
    }
}
