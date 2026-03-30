<?php

use App\Http\Middleware\Api\AuthenticateApiToken;
use App\Http\Middleware\App\EnsureSubscribed;
use App\Http\Middleware\App\HandleAppearance;
use App\Http\Middleware\App\HandleInertiaRequests;
use App\Http\Middleware\App\SetLocale;
use App\Http\Middleware\Mcp\AuthenticateMcpToken;
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

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'locale']);

        $middleware->web(append: [
            SetLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'subscribed' => EnsureSubscribed::class,
            'api.auth' => AuthenticateApiToken::class,
            'mcp.auth' => AuthenticateMcpToken::class,
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
