<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialPlatform;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function connect(Request $request, Workspace $workspace): Response
    {
        $this->ensurePlatformEnabled();
        $this->authorize('manageAccounts', $workspace);

        if ($workspace->hasConnectedPlatform($this->platform->value)) {
            return back()->with('error', 'This platform is already connected.');
        }

        return $this->redirectToProvider($workspace, $this->driver, $this->scopes);
    }

    public function callback(Request $request): RedirectResponse
    {
        return $this->handleCallback($request, $this->platform, $this->driver);
    }
}
