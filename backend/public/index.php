<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// t_a0d9ee0f (D3) — php artisan serve / PHP built-in server "index proximity" bug:
// request /app/que/82 được CLI server ánh xạ SCRIPT_NAME=/app/index.html +
// PATH_INFO=/que/82 (vì public/app/index.html tồn tại). Symfony Request::capture
// suy baseUrl='/app' → route path còn 'que/82' → /app/{any?} KHÔNG match → 404
// chỉ xảy ra trên built-in server; nginx/apache (production) không có hiện tượng
// này. Trả SCRIPT_NAME về đúng entry-point gốc TRƯỚC khi capture, cli-server only.
if (PHP_SAPI === 'cli-server' && ($_SERVER['SCRIPT_NAME'] ?? '') !== '/index.php') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
    unset($_SERVER['PATH_INFO'], $_SERVER['ORIG_PATH_INFO']);
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
