<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialPlatform;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class XController extends SocialController
{
    protected string $driver = 'twitter';

    protected SocialPlatform $platform = SocialPlatform::X;

    protected array $scopes = [
        'tweet.read',
        'tweet.write',
        'users.read',
        'offline.access',
    ];

    public function connect(Request $request, Workspace $workspace): Response
    {
        $this->authorize('update', $workspace);

        if ($workspace->hasConnectedPlatform($this->platform->value)) {
            return back()->with('error', 'Esta plataforma já está conectada.');
        }

        return $this->redirectToProvider($workspace, $this->driver, $this->scopes);
    }

    public function callback(Request $request): RedirectResponse
    {
        return $this->handleCallback($request, $this->platform, $this->driver);
    }
}
