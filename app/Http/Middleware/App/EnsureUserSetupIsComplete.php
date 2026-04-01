<?php

declare(strict_types=1);

namespace App\Http\Middleware\App;

use App\Enums\User\Setup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserSetupIsComplete
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // If setup is completed, allow through
        if ($user->setup === Setup::Completed) {
            return $next($request);
        }

        // Map setup status to allowed routes
        $allowedRoutes = match ($user->setup) {
            Setup::Role => ['app.onboarding.role', 'app.onboarding.role.store'],
            Setup::Connections => ['app.onboarding.connect', 'app.onboarding.connect.store', 'app.social.*'],
            Setup::Subscription => ['app.subscribe', 'app.billing.*', 'app.onboarding.complete'],
            default => ['app.onboarding.role', 'app.onboarding.role.store'],
        };

        $currentRoute = $request->route()?->getName();

        // Check if current route is allowed
        foreach ($allowedRoutes as $pattern) {
            if ($currentRoute === $pattern || fnmatch($pattern, $currentRoute ?? '')) {
                return $next($request);
            }
        }

        // Redirect to appropriate step
        return match ($user->setup) {
            Setup::Role => redirect()->route('app.onboarding.role'),
            Setup::Connections => redirect()->route('app.onboarding.connect'),
            Setup::Subscription => redirect()->route('app.subscribe'),
            default => redirect()->route('app.onboarding.role'),
        };
    }
}
