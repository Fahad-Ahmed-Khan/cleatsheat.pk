<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\Api\ApiResponder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'payments/callback/*',
            'webhooks/safepay',
            'webhooks/shipping/*',
            'webhooks/whatsapp',
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponder::error(
                    $e->getMessage(),
                    $e->status,
                    $e->errors(),
                    'validation_failed',
                );
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponder::error(
                    $e->getMessage() ?: 'Unauthenticated.',
                    401,
                    code: 'unauthenticated',
                );
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponder::error(
                    $e->getMessage() ?: 'Forbidden.',
                    403,
                    code: 'forbidden',
                );
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') || $e instanceof ValidationException || $e instanceof AuthenticationException || $e instanceof AuthorizationException) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface) {
                return ApiResponder::error(
                    $e->getMessage() ?: 'HTTP error',
                    $e->getStatusCode(),
                    code: null,
                );
            }

            if (config('app.debug')) {
                return ApiResponder::error(
                    $e->getMessage().' ['.class_basename($e::class).']',
                    500,
                    code: 'server_error',
                );
            }

            return ApiResponder::error('Server error', 500);
        });
    })->create();
