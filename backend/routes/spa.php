<?php

use Illuminate\Support\Facades\Route;

/*
| BUGFIX t_a0d9ee0f (D3) — SPA fallback cho Vue router (04-ui S3 deep-link /que/:drawId).
| FE build ra public/app/ (base '/app/'), router dùng history mode: client-nav OK nhưng
| F5/chia sẻ link /app/que/82 từng trả Laravel 404 — Vite dev 5173 có fallback nên
| dev không thấy. Route này serve index.html cho MỌI /app/* còn lại; file thật
| (assets/*) được web server phục TRƯỚC khi tới Laravel (php -S + nginx try_files)
| nên không bị nuốt. 404 khi chưa build (index.html vắng) — không 500.
*/

Route::get('/app/{any?}', function (string $any = '') {
    $index = public_path('app/index.html');
    abort_unless(is_readable($index), 404);

    return response(file_get_contents($index), 200, [
        'Content-Type' => 'text/html; charset=UTF-8',
        // HTML deep-link không cache: sau deploy asset hash đổi, index cũ trong
        // cache sẽ đòi file 404.
        'Cache-Control' => 'no-store, must-revalidate',
    ]);
})->where('any', '.*')->name('spa.fallback');
