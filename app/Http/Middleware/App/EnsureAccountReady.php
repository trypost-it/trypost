<?php

declare(strict_types=1);

namespace App\Http\Middleware\App;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountReady
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $account = $user->account;

        if (! $account || ! $account->hasUsableAccess()) {
            return redirect()->route('app.subscribe');
        }

        if (! $user->workspaces()->exists()) {
            return redirect()->route('app.workspaces.create');
        }

        return $next($request);
    }
}
