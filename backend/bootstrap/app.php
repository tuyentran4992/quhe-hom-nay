<?php

use App\Exceptions\ApiException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // BE-1 — mọi endpoint /api lỗi trả ĐÚNG error envelope §0.3 (specs/1.mvp/03-api.md):
        // { "error": { code, message, details } }. Laravel chạy renderCallbacks THEO THỨ TỰ
        // ĐĂNG KÝ (FIFO — Handler.php:682) → catch-all Throwable phải đăng ký CUỐI CÙNG,
        // nếu không nó nuốt hết các handler cụ thể bên dưới.

        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json($e->toEnvelope(), $e->status());
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Dữ liệu không hợp lệ.',
                    'details' => ['errors' => $e->errors()],
                ]], 422);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Không tìm thấy tài nguyên.',
                ]], 404);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Phiên không hợp lệ.',
                ]], 401);
            }
        });

        // route /api không tồn tại + 500 crash không lộ stack (§0.3 bảng mã)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Không tìm thấy tài nguyên.',
                ]], 404);
            }
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') && config('app.debug') === false) {
                report($e);

                return response()->json(['error' => [
                    'code' => 'INTERNAL',
                    'message' => 'Lỗi hệ thống. Thử lại sau.',
                ]], 500);
            }
        });
    })->create();
