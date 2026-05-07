<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class BlueskyController extends SocialController
{
    protected SocialPlatform $platform = SocialPlatform::Bluesky;

    public function connect(Request $request): Response|RedirectResponse
    {
        $this->ensurePlatformEnabled();

        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);
        $this->ensureSocialAccountLimit($workspace);

        return Inertia::render('accounts/BlueskyConnect', [
            'errors' => session('errors')?->getBag('default')?->toArray() ?? [],
        ]);
    }

    public function store(Request $request): View|RedirectResponse
    {
        $this->ensurePlatformEnabled();

        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string|min:3',
        ]);

        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

        $service = 'https://bsky.social';

        try {
            // Authenticate with Bluesky
            $response = Http::post("{$service}/xrpc/com.atproto.server.createSession", [
                'identifier' => $request->identifier,
                'password' => $request->password,
            ]);

            if ($response->failed()) {
                Log::error('Bluesky authentication failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $errorMessage = 'Invalid credentials';
                $body = $response->json();
                if (isset($body['message'])) {
                    $errorMessage = $body['message'];
                }

                return back()->withErrors(['password' => $errorMessage]);
            }

            $data = $response->json();

            // Get profile
            $profileResponse = Http::withToken(data_get($data, 'accessJwt'))
                ->get("{$service}/xrpc/app.bsky.actor.getProfile", [
                    'actor' => data_get($data, 'did'),
                ]);

            $profile = $profileResponse->successful() ? $profileResponse->json() : [];

            $avatarPath = data_get($profile, 'avatar') ? uploadFromUrl(data_get($profile, 'avatar')) : null;

            $workspace->socialAccounts()->updateOrCreate(
                [
                    'platform' => $this->platform->value,
                    'platform_user_id' => data_get($data, 'did'),
                ],
                [
                    'username' => data_get($data, 'handle'),
                    'display_name' => data_get($profile, 'displayName', data_get($data, 'handle')),
                    'avatar_url' => $avatarPath,
                    'access_token' => data_get($data, 'accessJwt'),
                    'refresh_token' => data_get($data, 'refreshJwt'),
                    'token_expires_at' => now()->addHours(2),
                    'status' => Status::Connected,
                    'error_message' => null,
                    'disconnected_at' => null,
                    'meta' => [
                        'service' => $service,
                        'identifier' => $request->identifier,
                        'password' => encrypt($request->password),
                    ],
                ],
            );

            return $this->popupCallback(true, __('accounts.popup_callback.connected'), $this->platform->value);
        } catch (\Exception $e) {
            Log::error('Bluesky connection error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['password' => 'Error connecting to Bluesky. Please try again.']);
        }
    }
}
