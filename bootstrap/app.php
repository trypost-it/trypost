<?php

use App\Http\Middleware\Api\AuthenticateApiToken;
use App\Http\Middleware\EnsureSubscribed;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'subscribed' => EnsureSubscribed::class,
            'api.auth' => AuthenticateApiToken::class,
        ]);

        $middleware->preventRequestForgery(except: [
            'stripe/*',
        ]);

        $middleware->prependToPriorityList(
            ThrottleRequests::class,
            AuthenticateApiToken::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                $retryAfter = $e->getHeaders()['Retry-After'] ?? null;
                $message = $retryAfter
                    ? "Rate limit exceeded. Please retry after {$retryAfter} seconds."
                    : 'Rate limit exceeded. Please try again later.';

                return response()->json([
                    'name' => 'rate_limit_exceeded',
                    'message' => $message,
                ], 429)->withHeaders($e->getHeaders());
            }
        });
    })->create();
