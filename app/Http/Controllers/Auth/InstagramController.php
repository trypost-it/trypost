<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialPlatform;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class InstagramController extends SocialController
{
    protected string $driver = 'instagram';

    protected SocialPlatform $platform = SocialPlatform::Instagram;

    protected array $scopes = [
        'instagram_basic',
        'instagram_content_publish',
        'pages_show_list',
        'pages_read_engagement',
    ];

    public function connect(Request $request, Workspace $workspace): Response
    {
        $this->ensurePlatformEnabled();
        $this->authorize('manageAccounts', $workspace);

        if ($workspace->hasConnectedPlatform($this->platform->value)) {
            return back()->with('error', 'This platform is already connected.');
        }

        session(['social_connect_workspace' => $workspace->id]);

        return Inertia::location(
            Socialite::driver($this->driver)
                ->scopes($this->scopes)
                ->redirect()
                ->getTargetUrl()
        );
    }

    public function callback(Request $request): RedirectResponse
    {
        $workspaceId = session('social_connect_workspace');

        if (! $workspaceId) {
            return redirect()->route('workspaces.index')
                ->with('error', 'Session expired. Please try again.');
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            return redirect()->route('workspaces.index')
                ->with('error', 'Workspace not found.');
        }

        if ($workspace->hasConnectedPlatform($this->platform->value)) {
            return redirect()->route('workspaces.accounts', $workspace)
                ->with('error', 'This platform is already connected.');
        }

        try {
            $socialUser = Socialite::driver($this->driver)->user();

            // Fetch Instagram accounts linked to Facebook pages
            $accounts = $this->fetchInstagramAccounts($socialUser->token);

            if (empty($accounts)) {
                return redirect()->route('workspaces.accounts', $workspace)
                    ->with('error', 'No Instagram Business accounts found. Make sure your Instagram is connected to a Facebook Page.');
            }

            // If only one account, connect directly
            if (count($accounts) === 1) {
                $account = $accounts[0];
                $avatarPath = uploadFromUrl($account['profile_picture_url']);

                $workspace->socialAccounts()->create([
                    'platform' => $this->platform->value,
                    'platform_user_id' => $account['id'],
                    'username' => $account['username'],
                    'display_name' => $account['name'] ?? $account['username'],
                    'avatar_url' => $avatarPath,
                    'access_token' => $account['page_access_token'],
                    'refresh_token' => null,
                    'token_expires_at' => null,
                    'scopes' => $this->scopes,
                    'meta' => [
                        'instagram_id' => $account['id'],
                        'page_id' => $account['page_id'],
                        'user_id' => $socialUser->getId(),
                        'user_token' => $socialUser->token,
                    ],
                ]);

                session()->forget('social_connect_workspace');

                return redirect()->route('workspaces.accounts', $workspace)
                    ->with('success', 'Instagram account connected successfully!');
            }

            // Multiple accounts - store data and show selection
            session([
                'instagram_oauth' => [
                    'user_token' => $socialUser->token,
                    'user_id' => $socialUser->getId(),
                    'accounts' => $accounts,
                ],
            ]);

            return redirect()->route('social.instagram.select-account');
        } catch (\Exception $e) {
            Log::error('Instagram OAuth Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('workspaces.accounts', $workspace)
                ->with('error', 'Error connecting account. Please try again.');
        }
    }

    public function selectAccount(Request $request)
    {
        $oauthData = session('instagram_oauth');
        $workspaceId = session('social_connect_workspace');

        if (! $oauthData || ! $workspaceId) {
            return redirect()->route('workspaces.index')
                ->with('error', 'Session expired. Please try again.');
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace) {
            return redirect()->route('workspaces.index')
                ->with('error', 'Workspace not found.');
        }

        return Inertia::render('accounts/InstagramAccountSelect', [
            'workspace' => $workspace,
            'accounts' => $oauthData['accounts'],
        ]);
    }

    public function select(Request $request): RedirectResponse
    {
        $request->validate([
            'account_id' => 'required|string',
        ]);

        $oauthData = session('instagram_oauth');
        $workspaceId = session('social_connect_workspace');

        if (! $oauthData || ! $workspaceId) {
            return redirect()->route('workspaces.index')
                ->with('error', 'Session expired. Please try again.');
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            return redirect()->route('workspaces.index')
                ->with('error', 'Workspace not found.');
        }

        try {
            $selectedAccount = collect($oauthData['accounts'])->firstWhere('id', $request->account_id);

            if (! $selectedAccount) {
                return redirect()->route('social.instagram.select-account')
                    ->with('error', 'Account not found.');
            }

            $avatarPath = uploadFromUrl($selectedAccount['profile_picture_url']);

            $workspace->socialAccounts()->create([
                'platform' => $this->platform->value,
                'platform_user_id' => $selectedAccount['id'],
                'username' => $selectedAccount['username'],
                'display_name' => $selectedAccount['name'] ?? $selectedAccount['username'],
                'avatar_url' => $avatarPath,
                'access_token' => $selectedAccount['page_access_token'],
                'refresh_token' => null,
                'token_expires_at' => null,
                'scopes' => $this->scopes,
                'meta' => [
                    'instagram_id' => $selectedAccount['id'],
                    'page_id' => $selectedAccount['page_id'],
                    'user_id' => $oauthData['user_id'],
                    'user_token' => $oauthData['user_token'],
                ],
            ]);

            session()->forget(['instagram_oauth', 'social_connect_workspace']);

            return redirect()->route('workspaces.accounts', $workspace)
                ->with('success', 'Instagram account connected successfully!');
        } catch (\Exception $e) {
            Log::error('Instagram account selection error', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('workspaces.accounts', $workspace)
                ->with('error', 'Error connecting account. Please try again.');
        }
    }

    private function fetchInstagramAccounts(string $userToken): array
    {
        try {
            // First, get all pages with their Instagram business accounts
            $response = Http::get('https://graph.facebook.com/v21.0/me/accounts', [
                'access_token' => $userToken,
                'fields' => 'id,name,access_token,instagram_business_account{id,username,name,profile_picture_url,followers_count}',
            ]);

            if ($response->failed()) {
                Log::error('Instagram accounts fetch failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $data = $response->json();
            $accounts = [];

            foreach ($data['data'] ?? [] as $page) {
                if (isset($page['instagram_business_account'])) {
                    $ig = $page['instagram_business_account'];
                    $accounts[] = [
                        'id' => $ig['id'],
                        'username' => $ig['username'],
                        'name' => $ig['name'] ?? $ig['username'],
                        'profile_picture_url' => $ig['profile_picture_url'] ?? null,
                        'followers_count' => $ig['followers_count'] ?? 0,
                        'page_id' => $page['id'],
                        'page_name' => $page['name'],
                        'page_access_token' => $page['access_token'],
                    ];
                }
            }

            return $accounts;
        } catch (\Exception $e) {
            Log::error('Instagram accounts fetch error', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
