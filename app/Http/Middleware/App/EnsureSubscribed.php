<?php

declare(strict_types=1);

namespace App\Http\Middleware\App;

use App\Enums\User\Setup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('trypost.self_hosted')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Let onboarding middleware handle users still in setup
        if ($user->setup !== Setup::Completed) {
            return $next($request);
        }

        $account = $user->account;

        if ($account && $account->hasActiveSubscription()) {
            return $next($request);
        }

        return redirect()->route('app.subscribe');
    }
}
