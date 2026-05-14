<?php

declare(strict_types=1);

namespace App\Http\Middleware\App;

use App\Actions\Plan\DetectPlanViolations;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanCompliance
{
    /**
     * Routes exempt from the compliance check.
     * Users must always be able to reach cleanup and upgrade pages.
     *
     * @var list<string>
     */
    private const EXEMPT_ROUTES = [
        'app.compliance.index',
        'app.accounts',
        'app.accounts.*',
        'app.workspaces.index',
        'app.workspaces.*',
        'app.workspace.*',
        'app.members',
        'app.members.*',
        'app.invites.*',
        'app.posts.index',
        'app.posts.*',
        'app.subscribe',
        'app.billing.*',
        'app.profile.*',
        'app.settings',
        'app.account.*',
        'app.authentication.*',
        'app.notifications.*',
        'app.usage.*',
        'logout',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user()?->account;

        if (! $account) {
            return $next($request);
        }

        foreach (self::EXEMPT_ROUTES as $pattern) {
            if ($request->routeIs($pattern)) {
                return $next($request);
            }
        }

        $violations = DetectPlanViolations::execute($account);

        if (empty($violations)) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => __('compliance.flash.plan_compliance_required'),
                'upgrade_required' => true,
                'reason' => 'plan_compliance_required',
                'violations' => $violations,
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        return redirect()->route('app.compliance.index');
    }
}
