<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\Status;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MastodonController extends SocialController
{
    protected SocialPlatform $platform = SocialPlatform::Mastodon;

    private const SCOPES = 'read:accounts write:statuses write:media';

    /**
     * Show form to enter Mastodon instance URL
     */
    public function connect(Request $request): Response|RedirectResponse
    {
        $this->ensurePlatformEnabled();

        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);
        $this->ensureSocialAccountLimit($workspace);

        return Inertia::render('accounts/MastodonConnect', [
            'errors' => session('errors')?->getBag('default')?->toArray() ?? [],
        ]);
    }

    /**
     * Register app on instance and redirect to OAuth
     */
    public function authorizeInstance(Request $request): SymfonyResponse|RedirectResponse
    {
        $this->ensurePlatformEnabled();

        $request->validate([
            'instance' => 'required|url',
        ]);

        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

        $instance = rtrim($request->instance, '/');

        try {
            // Register app on the instance
            $appResponse = Http::post("{$instance}/api/v1/apps", [
                'client_name' => config('app.name'),
                'redirect_uris' => route('app.social.mastodon.callback'),
                'scopes' => self::SCOPES,
                'website' => config('app.url'),
            ]);

            if ($appResponse->failed()) {
                Log::error('Mastodon app registration failed', [
                    'instance' => $instance,
                    'status' => $appResponse->status(),
                    'body' => $appResponse->body(),
                ]);

                return back()->withErrors(['instance' => 'Could not connect to this Mastodon instance.']);
            }

            $app = $appResponse->json();

            // Store in session for callback
            $state = bin2hex(random_bytes(16));
            session([
                'mastodon_instance' => $instance,
                'mastodon_client_id' => $app['client_id'],
                'mastodon_client_secret' => $app['client_secret'],
                'mastodon_oauth_state' => $state,
                'social_connect_workspace' => $workspace->id,
            ]);

            // Redirect to OAuth
            $params = http_build_query([
                'client_id' => $app['client_id'],
                'response_type' => 'code',
                'redirect_uri' => route('app.social.mastodon.callback'),
                'scope' => self::SCOPES,
                'state' => $state,
            ]);

            return Inertia::location("{$instance}/oauth/authorize?{$params}");
        } catch (\Exception $e) {
            Log::error('Mastodon connection error', [
                'instance' => $instance,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['instance' => 'Error connecting to Mastodon instance.']);
        }
    }

    /**
     * Handle OAuth callback
     */
    public function callback(Request $request): View
    {
        $workspaceId = session('social_connect_workspace');
        $savedState = session('mastodon_oauth_state');
        $instance = session('mastodon_instance');
        $clientId = session('mastodon_client_id');
        $clientSecret = session('mastodon_client_secret');

        if (! $workspaceId || ! $instance) {
            $this->clearMastodonSession();

            return $this->popupCallback(false, 'Session expired. Please try again.', $this->platform->value);
        }

        if ($request->state !== $savedState) {
            $this->clearMastodonSession();

            return $this->popupCallback(false, 'Invalid state. Please try again.', $this->platform->value);
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            $this->clearMastodonSession();

            return $this->popupCallback(false, 'Workspace not found.', $this->platform->value);
        }

        try {
            // Exchange code for token
            $tokenResponse = Http::asForm()->post("{$instance}/oauth/token", [
                'grant_type' => 'authorization_code',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => route('app.social.mastodon.callback'),
                'code' => $request->code,
            ]);

            if ($tokenResponse->failed()) {
                Log::error('Mastodon token exchange failed', [
                    'status' => $tokenResponse->status(),
                    'body' => $tokenResponse->body(),
                ]);
                $this->clearMastodonSession();

                return $this->popupCallback(false, 'Failed to authenticate.', $this->platform->value);
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'];

            // Get user profile
            $profileResponse = Http::withToken($accessToken)
                ->get("{$instance}/api/v1/accounts/verify_credentials");

            if ($profileResponse->failed()) {
                $this->clearMastodonSession();

                return $this->popupCallback(false, 'Failed to get profile.', $this->platform->value);
            }

            $profile = $profileResponse->json();

            $avatarPath = data_get($profile, 'avatar') ? uploadFromUrl(data_get($profile, 'avatar')) : null;

            // Mastodon returns the granted scopes in the token response as a
            // space-separated string. We persist them so the publisher can
            // verify required scopes (write:statuses, write:media) before
            // attempting to post.
            $grantedScopes = array_values(array_filter(explode(' ', (string) data_get($tokenData, 'scope', self::SCOPES))));

            $workspace->socialAccounts()->updateOrCreate(
                [
                    'platform' => $this->platform->value,
                    'platform_user_id' => data_get($profile, 'id'),
                ],
                [
                    'username' => data_get($profile, 'acct'),
                    'display_name' => data_get($profile, 'display_name') ?: data_get($profile, 'username'),
                    'avatar_url' => $avatarPath,
                    'access_token' => $accessToken,
                    'refresh_token' => null,
                    'token_expires_at' => null,
                    'scopes' => $grantedScopes,
                    'status' => Status::Connected,
                    'error_message' => null,
                    'disconnected_at' => null,
                    'meta' => [
                        'instance' => $instance,
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                    ],
                ],
            );

            $this->clearMastodonSession();

            return $this->popupCallback(true, 'Mastodon account connected!', $this->platform->value);
        } catch (\Exception $e) {
            Log::error('Mastodon callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->clearMastodonSession();

            return $this->popupCallback(false, 'Error connecting account.', $this->platform->value);
        }
    }

    private function clearMastodonSession(): void
    {
        session()->forget([
            'mastodon_instance',
            'mastodon_client_id',
            'mastodon_client_secret',
            'mastodon_oauth_state',
            'social_connect_workspace',
        ]);
    }
}
