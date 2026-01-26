<?php

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
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class FacebookController extends SocialController
{
    protected string $driver = 'facebook';

    protected SocialPlatform $platform = SocialPlatform::Facebook;

    protected array $scopes = [
        'public_profile',
        'email',
        'pages_show_list',
        'pages_read_engagement',
        'pages_manage_posts',
    ];

    public function connect(Request $request): Response|RedirectResponse
    {
        $this->ensurePlatformEnabled();

        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

        $existingAccount = $workspace->socialAccounts()
            ->where('platform', $this->platform->value)
            ->first();

        if ($existingAccount && ! $existingAccount->isDisconnected()) {
            session()->flash('flash.banner', __('accounts.flash.already_connected'));
            session()->flash('flash.bannerStyle', 'danger');

            return back();
        }

        session([
            'social_connect_workspace' => $workspace->id,
            'social_reconnect_id' => $existingAccount?->id,
            'social_connect_onboarding' => $request->boolean('onboarding'),
        ]);

        return Inertia::location(
            Socialite::driver($this->driver)
                ->scopes($this->scopes)
                ->redirect()
                ->getTargetUrl()
        );
    }

    public function callback(Request $request): View|RedirectResponse
    {
        $workspaceId = session('social_connect_workspace');

        if (! $workspaceId) {
            return $this->popupCallback(false, 'Session expired. Please try again.', $this->platform->value);
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            return $this->popupCallback(false, 'Workspace not found.', $this->platform->value);
        }

        $reconnectId = session('social_reconnect_id');
        $existingAccount = $reconnectId ? $workspace->socialAccounts()->find($reconnectId) : null;

        // If account exists and is connected, don't allow duplicate
        if (! $existingAccount && $workspace->hasConnectedPlatform($this->platform->value)) {
            return $this->popupCallback(false, 'This platform is already connected.', $this->platform->value);
        }

        try {
            $socialUser = Socialite::driver($this->driver)->user();

            // Fetch pages the user manages
            $pages = $this->fetchPages($socialUser->token);

            if (empty($pages)) {
                return $this->popupCallback(false, 'No Facebook Pages found. You need to be an admin of at least one page.', $this->platform->value);
            }

            // If only one page, connect directly
            if (count($pages) === 1) {
                $page = $pages[0];
                $avatarPath = uploadFromUrl($page['picture']);

                if ($existingAccount) {
                    // Reconnect existing account
                    $existingAccount->update([
                        'platform_user_id' => $page['id'],
                        'username' => $page['username'] ?? null,
                        'display_name' => $page['name'],
                        'avatar_url' => $avatarPath,
                        'access_token' => $page['access_token'],
                        'refresh_token' => null,
                        'token_expires_at' => null,
                        'scopes' => $this->scopes,
                        'meta' => [
                            'page_id' => $page['id'],
                            'user_id' => $socialUser->getId(),
                            'user_token' => $socialUser->token,
                        ],
                    ]);
                    $existingAccount->markAsConnected();

                    session()->forget('social_reconnect_id');

                    return $this->popupCallback(true, 'Facebook Page reconnected!', $this->platform->value);
                }

                // Create new account
                $workspace->socialAccounts()->create([
                    'platform' => $this->platform->value,
                    'platform_user_id' => $page['id'],
                    'username' => $page['username'] ?? null,
                    'display_name' => $page['name'],
                    'avatar_url' => $avatarPath,
                    'access_token' => $page['access_token'],
                    'refresh_token' => null, // Page tokens don't expire if user token is long-lived
                    'token_expires_at' => null,
                    'scopes' => $this->scopes,
                    'status' => Status::Connected,
                    'meta' => [
                        'page_id' => $page['id'],
                        'user_id' => $socialUser->getId(),
                        'user_token' => $socialUser->token,
                    ],
                ]);

                session()->forget('social_reconnect_id');

                return $this->popupCallback(true, 'Facebook Page connected!', $this->platform->value);
            }

            // Multiple pages - store data and show selection
            session([
                'facebook_oauth' => [
                    'user_token' => $socialUser->token,
                    'user_id' => $socialUser->getId(),
                    'pages' => $pages,
                    'reconnect_id' => $reconnectId,
                ],
            ]);

            return redirect()->route('social.facebook.select-page');
        } catch (\Exception $e) {
            Log::error('Facebook OAuth Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->popupCallback(false, 'Error connecting account. Please try again.', $this->platform->value);
        }
    }

    public function selectPage(Request $request)
    {
        $oauthData = session('facebook_oauth');
        $workspaceId = session('social_connect_workspace');

        if (! $oauthData || ! $workspaceId) {
            session()->flash('flash.banner', __('accounts.flash.session_expired'));
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('accounts');
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace) {
            session()->flash('flash.banner', __('accounts.flash.workspace_not_found'));
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('accounts');
        }

        return Inertia::render('accounts/FacebookPageSelect', [
            'workspace' => $workspace,
            'pages' => $oauthData['pages'],
        ]);
    }

    public function select(Request $request): View
    {
        $request->validate([
            'page_id' => 'required|string',
        ]);

        $oauthData = session('facebook_oauth');
        $workspaceId = session('social_connect_workspace');

        if (! $oauthData || ! $workspaceId) {
            return $this->popupCallback(false, 'Session expired. Please try again.', $this->platform->value);
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            return $this->popupCallback(false, 'Workspace not found.', $this->platform->value);
        }

        try {
            $selectedPage = collect($oauthData['pages'])->firstWhere('id', $request->page_id);

            if (! $selectedPage) {
                return $this->popupCallback(false, 'Page not found.', $this->platform->value);
            }

            $avatarPath = uploadFromUrl($selectedPage['picture']);
            $reconnectId = $oauthData['reconnect_id'] ?? null;

            if ($reconnectId) {
                // Reconnect existing account
                $existingAccount = $workspace->socialAccounts()->find($reconnectId);

                if ($existingAccount) {
                    $existingAccount->update([
                        'platform_user_id' => $selectedPage['id'],
                        'username' => $selectedPage['username'] ?? null,
                        'display_name' => $selectedPage['name'],
                        'avatar_url' => $avatarPath,
                        'access_token' => $selectedPage['access_token'],
                        'refresh_token' => null,
                        'token_expires_at' => null,
                        'scopes' => $this->scopes,
                        'meta' => [
                            'page_id' => $selectedPage['id'],
                            'user_id' => $oauthData['user_id'],
                            'user_token' => $oauthData['user_token'],
                        ],
                    ]);
                    $existingAccount->markAsConnected();

                    session()->forget(['facebook_oauth', 'social_reconnect_id']);

                    return $this->popupCallback(true, 'Facebook Page reconnected!', $this->platform->value);
                }
            }

            // Create new account
            $workspace->socialAccounts()->create([
                'platform' => $this->platform->value,
                'platform_user_id' => $selectedPage['id'],
                'username' => $selectedPage['username'] ?? null,
                'display_name' => $selectedPage['name'],
                'avatar_url' => $avatarPath,
                'access_token' => $selectedPage['access_token'],
                'refresh_token' => null,
                'token_expires_at' => null,
                'scopes' => $this->scopes,
                'status' => Status::Connected,
                'meta' => [
                    'page_id' => $selectedPage['id'],
                    'user_id' => $oauthData['user_id'],
                    'user_token' => $oauthData['user_token'],
                ],
            ]);

            session()->forget(['facebook_oauth', 'social_reconnect_id']);

            return $this->popupCallback(true, 'Facebook Page connected!', $this->platform->value);
        } catch (\Exception $e) {
            Log::error('Facebook page selection error', [
                'error' => $e->getMessage(),
            ]);

            return $this->popupCallback(false, 'Error connecting page. Please try again.', $this->platform->value);
        }
    }

    private function fetchPages(string $userToken): array
    {
        try {
            $response = Http::get('https://graph.facebook.com/v24.0/me/accounts', [
                'access_token' => $userToken,
                'fields' => 'id,name,username,picture{url},access_token',
            ]);

            if ($response->failed()) {
                Log::error('Facebook pages fetch failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $data = $response->json();

            return collect($data['data'] ?? [])->map(fn ($page) => [
                'id' => $page['id'],
                'name' => $page['name'],
                'username' => $page['username'] ?? null,
                'picture' => $page['picture']['data']['url'] ?? null,
                'access_token' => $page['access_token'],
            ])->toArray();
        } catch (\Exception $e) {
            Log::error('Facebook pages fetch error', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
