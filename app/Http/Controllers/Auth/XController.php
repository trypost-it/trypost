<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Features\BlockedNetworks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

class XController extends SocialController
{
    protected string $driver = 'x';

    protected SocialPlatform $platform = SocialPlatform::X;

    protected array $scopes = [
        'tweet.read',
        'tweet.write',
        'users.read',
        'media.write',
        'offline.access',
    ];

    public function connect(Request $request): Response|RedirectResponse
    {
        $this->ensurePlatformEnabled();

        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $blockedNetworks = Feature::for($workspace->account)->value(BlockedNetworks::class);

        if (in_array('x', $blockedNetworks, true)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => __('billing.flash.x_disabled'),
                    'upgrade_required' => true,
                    'reason' => 'network_disabled',
                ], Response::HTTP_PAYMENT_REQUIRED);
            }

            return redirect()->route('app.subscribe')->with('upgrade_reason', 'network_disabled');
        }

        $this->authorize('manageAccounts', $workspace);

        return $this->redirectToProvider($request, $this->driver, $this->scopes);
    }

    public function callback(Request $request): View
    {
        return $this->handleCallback($request, $this->platform, $this->driver);
    }
}
