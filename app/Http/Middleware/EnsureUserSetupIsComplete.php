<?php

namespace App\Http\Middleware;

use App\Enums\User\Setup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserSetupIsComplete
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
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
            Setup::Role => ['onboarding.step1', 'onboarding.step1.store'],
            Setup::Connections => ['onboarding.step2', 'onboarding.step2.store', 'social.*'],
            Setup::Subscription => ['onboarding.complete', 'onboarding.step2'],
            default => ['onboarding.step1', 'onboarding.step1.store'],
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
            Setup::Role => redirect()->route('onboarding.step1'),
            Setup::Connections => redirect()->route('onboarding.step2'),
            Setup::Subscription => redirect()->route('onboarding.step2'),
            default => redirect()->route('onboarding.step1'),
        };
    }
}
