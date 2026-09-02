<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| BE-PAY-EXPIRE (t_bbfff19b) — reconcile đơn pending quá TTL mỗi phút.
| Cờ C-15 (project.pay.expire_cron_enabled) tắt → bản thân lệnh no-op,
| nên cứ khai báo; chỉ nổ lực khi ops gắn `* * * * * php artisan schedule:run`
| (hạ tầng MVP chưa có crond — 01 §2). Lệnh idempotent, chạy chồng vô hại.
*/
Schedule::command('payments:expire-pending')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
