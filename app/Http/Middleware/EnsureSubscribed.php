<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscribed
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip subscription check for self-hosted mode
        if (config('trypost.self_hosted')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Allow access if user has active subscription or is on trial
        if ($user->subscribed('default') || $user->onTrial('default')) {
            return $next($request);
        }

        // Allow access if user belongs to a workspace owned by a subscribed user
        $currentWorkspace = $user->currentWorkspace;

        if ($currentWorkspace && $currentWorkspace->owner && $currentWorkspace->owner->id !== $user->id) {
            $owner = $currentWorkspace->owner;

            if ($owner->subscribed('default') || $owner->onTrial('default')) {
                return $next($request);
            }
        }

        // Redirect to subscription page
        return redirect()->route('app.subscribe');
    }
}
