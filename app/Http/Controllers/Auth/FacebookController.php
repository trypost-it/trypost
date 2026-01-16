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

class FacebookController extends SocialController
{
    protected string $driver = 'facebook';

    protected SocialPlatform $platform = SocialPlatform::Facebook;

    protected array $scopes = [
        'pages_show_list',
        'pages_read_engagement',
        'pages_manage_posts',
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

            // Fetch pages the user manages
            $pages = $this->fetchPages($socialUser->token);

            if (empty($pages)) {
                return redirect()->route('workspaces.accounts', $workspace)
                    ->with('error', 'No Facebook Pages found. You need to be an admin of at least one page.');
            }

            // If only one page, connect directly
            if (count($pages) === 1) {
                $page = $pages[0];
                $avatarPath = uploadFromUrl($page['picture']);

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
                    'meta' => [
                        'page_id' => $page['id'],
                        'user_id' => $socialUser->getId(),
                        'user_token' => $socialUser->token,
                    ],
                ]);

                session()->forget('social_connect_workspace');

                return redirect()->route('workspaces.accounts', $workspace)
                    ->with('success', 'Facebook Page connected successfully!');
            }

            // Multiple pages - store data and show selection
            session([
                'facebook_oauth' => [
                    'user_token' => $socialUser->token,
                    'user_id' => $socialUser->getId(),
                    'pages' => $pages,
                ],
            ]);

            return redirect()->route('social.facebook.select-page');
        } catch (\Exception $e) {
            Log::error('Facebook OAuth Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('workspaces.accounts', $workspace)
                ->with('error', 'Error connecting account. Please try again.');
        }
    }

    public function selectPage(Request $request)
    {
        $oauthData = session('facebook_oauth');
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

        return Inertia::render('accounts/FacebookPageSelect', [
            'workspace' => $workspace,
            'pages' => $oauthData['pages'],
        ]);
    }

    public function select(Request $request): RedirectResponse
    {
        $request->validate([
            'page_id' => 'required|string',
        ]);

        $oauthData = session('facebook_oauth');
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
            $selectedPage = collect($oauthData['pages'])->firstWhere('id', $request->page_id);

            if (! $selectedPage) {
                return redirect()->route('social.facebook.select-page')
                    ->with('error', 'Page not found.');
            }

            $avatarPath = uploadFromUrl($selectedPage['picture']);

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
                'meta' => [
                    'page_id' => $selectedPage['id'],
                    'user_id' => $oauthData['user_id'],
                    'user_token' => $oauthData['user_token'],
                ],
            ]);

            session()->forget(['facebook_oauth', 'social_connect_workspace']);

            return redirect()->route('workspaces.accounts', $workspace)
                ->with('success', 'Facebook Page connected successfully!');
        } catch (\Exception $e) {
            Log::error('Facebook page selection error', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('workspaces.accounts', $workspace)
                ->with('error', 'Error connecting page. Please try again.');
        }
    }

    private function fetchPages(string $userToken): array
    {
        try {
            $response = Http::get('https://graph.facebook.com/v21.0/me/accounts', [
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
