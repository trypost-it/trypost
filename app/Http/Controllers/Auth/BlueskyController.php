<?php

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
            return redirect()->route('workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

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
            return redirect()->route('workspaces.create');
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
            $profileResponse = Http::withToken($data['accessJwt'])
                ->get("{$service}/xrpc/app.bsky.actor.getProfile", [
                    'actor' => $data['did'],
                ]);

            $profile = $profileResponse->successful() ? $profileResponse->json() : [];

            // Check existing
            $existingAccount = $workspace->socialAccounts()
                ->where('platform', $this->platform->value)
                ->first();

            if ($existingAccount && ! $existingAccount->isDisconnected()) {
                return back()->withErrors(['identifier' => 'Bluesky is already connected.']);
            }

            $avatarPath = isset($profile['avatar']) ? uploadFromUrl($profile['avatar']) : null;

            $accountData = [
                'platform' => $this->platform->value,
                'platform_user_id' => $data['did'],
                'username' => $data['handle'],
                'display_name' => $profile['displayName'] ?? $data['handle'],
                'avatar_url' => $avatarPath,
                'access_token' => $data['accessJwt'],
                'refresh_token' => $data['refreshJwt'],
                'token_expires_at' => now()->addHours(2),
                'meta' => [
                    'service' => $service,
                    'identifier' => $request->identifier,
                    'password' => encrypt($request->password),
                ],
            ];

            if ($existingAccount) {
                $existingAccount->update($accountData);
                $existingAccount->markAsConnected();

                return $this->popupCallback(true, 'Bluesky account reconnected!', $this->platform->value);
            }

            $accountData['status'] = Status::Connected;
            $workspace->socialAccounts()->create($accountData);

            return $this->popupCallback(true, 'Bluesky account connected!', $this->platform->value);
        } catch (\Exception $e) {
            Log::error('Bluesky connection error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['password' => 'Error connecting to Bluesky. Please try again.']);
        }
    }
}
