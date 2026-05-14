<?php

declare(strict_types=1);

namespace App\Http\Middleware\App;

use App\Features\CanUseAnalytics;
use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

class EnsureAnalyticsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user()?->account;

        if (! $account || ! Feature::for($account)->value(CanUseAnalytics::class)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => __('billing.flash.analytics_disabled'),
                    'upgrade_required' => true,
                    'reason' => 'analytics_disabled',
                ], Response::HTTP_PAYMENT_REQUIRED);
            }

            return redirect()
                ->route('app.subscribe')
                ->with('upgrade_reason', 'analytics_disabled');
        }

        return $next($request);
    }
}
