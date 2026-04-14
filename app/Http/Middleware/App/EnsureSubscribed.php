<?php

declare(strict_types=1);

namespace App\Http\Middleware\App;

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

        $workspace = $user->currentWorkspace;

        if ($workspace && $workspace->hasActiveSubscription()) {
            return $next($request);
        }

        return redirect()->route('app.subscribe');
    }
}
