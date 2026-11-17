<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureUserActive;
use App\Http\Middleware\ForceJson;
use App\Http\Middleware\RequestId;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api_v1.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            RequestId::class,
            ForceJson::class,
        ]);

        $middleware->alias([
            'active' => EnsureUserActive::class,
            'password.changed' => EnsurePasswordChanged::class,
            'permission' => CheckPermission::class,
        ]);

        // API-only: never redirect unauthenticated requests to a login page.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReport([ApiException::class]);

        $isApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->render(function (ApiException $e, Request $request) {
            return ApiResponse::error($e->getMessage(), $e->errorCode, $e->status, $e->errors);
        });

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('Validation failed.', 'VALIDATION_ERROR', 422, $e->errors());
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('Unauthenticated.', 'UNAUTHENTICATED', 401);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error($e->getMessage() ?: 'Forbidden.', 'FORBIDDEN', 403);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('Resource not found.', 'NOT_FOUND', 404);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('Method not allowed.', 'METHOD_NOT_ALLOWED', 405);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('Too many requests. Please slow down.', 'RATE_LIMITED', 429)
                    ->withHeaders($e->getHeaders());
            }
        });

        $exceptions->render(function (QueryException $e, Request $request) use ($isApi) {
            // 23000 = integrity constraint (duplicate key, FK restrict, CHECK)
            if ($isApi($request) && $e->getCode() === '23000') {
                return ApiResponse::error('The change conflicts with existing data.', 'CONFLICT', 409);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error($e->getMessage() ?: 'HTTP error.', 'HTTP_ERROR', $e->getStatusCode());
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                $message = config('app.debug')
                    ? get_class($e).': '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine()
                    : 'An unexpected error occurred.';

                return ApiResponse::error($message, 'SERVER_ERROR', 500);
            }
        });
    })->create();
