<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\Status;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class InstagramFacebookController extends SocialController
{
    protected string $driver = 'facebook';

    protected SocialPlatform $platform = SocialPlatform::InstagramFacebook;

    protected array $scopes = [
        'public_profile',
        'pages_show_list',
        'pages_read_engagement',
        'business_management',
        'instagram_basic',
        'instagram_content_publish',
        'instagram_manage_insights',
    ];

    public function connect(Request $request): Response|RedirectResponse
    {
        $this->ensurePlatformEnabled();

        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

        $existingAccount = $workspace->socialAccounts()
            ->where('platform', $this->platform->value)
            ->first();

        session([
            'social_connect_workspace' => $workspace->id,
            'social_reconnect_id' => $existingAccount?->id,
            'social_connect_onboarding' => $request->boolean('onboarding'),
        ]);

        $url = Socialite::driver($this->driver)
            ->usingGraphVersion('v20.0')
            ->setScopes($this->scopes)
            ->redirectUrl(route('app.social.instagram-facebook.callback'))
            ->redirect()
            ->getTargetUrl();

        return Inertia::location($url);
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

        try {
            $socialUser = Socialite::driver($this->driver)
                ->usingGraphVersion('v20.0')
                ->redirectUrl(route('app.social.instagram-facebook.callback'))
                ->user();

            $pages = $this->fetchPagesWithInstagram($socialUser->token);

            if (empty($pages)) {
                return $this->popupCallback(false, 'No Facebook Pages with linked Instagram accounts found.', $this->platform->value);
            }

            if (count($pages) === 1) {
                return $this->connectInstagramAccount($workspace, $pages[0], $existingAccount);
            }

            // Multiple pages — show selection
            session([
                'instagram_facebook_oauth' => [
                    'user_token' => $socialUser->token,
                    'pages' => $pages,
                    'reconnect_id' => $reconnectId,
                ],
            ]);

            return redirect()->route('app.social.instagram-facebook.select-page');
        } catch (\Exception $e) {
            Log::error('Instagram via Facebook OAuth Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->popupCallback(false, 'Error connecting account. Please try again.', $this->platform->value);
        }
    }

    public function selectPage(Request $request)
    {
        $oauthData = session('instagram_facebook_oauth');
        $workspaceId = session('social_connect_workspace');

        if (! $oauthData || ! $workspaceId) {
            session()->flash('flash.banner', 'Session expired. Please try again.');
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('app.accounts');
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace) {
            return redirect()->route('app.accounts');
        }

        $pages = collect(data_get($oauthData, 'pages'))
            ->map(fn ($page) => Arr::except($page, ['page_access_token']))
            ->toArray();

        return Inertia::render('accounts/InstagramFacebookPageSelect', [
            'workspace' => $workspace,
            'pages' => $pages,
        ]);
    }

    public function select(Request $request): View
    {
        $request->validate([
            'page_id' => 'required|string',
        ]);

        $oauthData = session('instagram_facebook_oauth');
        $workspaceId = session('social_connect_workspace');

        if (! $oauthData || ! $workspaceId) {
            return $this->popupCallback(false, 'Session expired. Please try again.', $this->platform->value);
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            return $this->popupCallback(false, 'Workspace not found.', $this->platform->value);
        }

        $reconnectId = data_get($oauthData, 'reconnect_id');
        $existingAccount = $reconnectId ? $workspace->socialAccounts()->find($reconnectId) : null;

        try {
            $selectedPage = collect(data_get($oauthData, 'pages'))->firstWhere('page_id', $request->page_id);

            if (! $selectedPage) {
                return $this->popupCallback(false, 'Page not found.', $this->platform->value);
            }

            $result = $this->connectInstagramAccount($workspace, $selectedPage, $existingAccount);

            session()->forget(['instagram_facebook_oauth', 'social_reconnect_id']);

            return $result;
        } catch (\Exception $e) {
            Log::error('Instagram via Facebook page selection error', ['error' => $e->getMessage()]);

            return $this->popupCallback(false, 'Error connecting account. Please try again.', $this->platform->value);
        }
    }

    private function connectInstagramAccount(Workspace $workspace, array $pageData, $existingAccount): View
    {
        $avatarPath = data_get($pageData, 'ig_picture') ? uploadFromUrl(data_get($pageData, 'ig_picture')) : null;

        $accountData = [
            'platform_user_id' => data_get($pageData, 'ig_id'),
            'username' => data_get($pageData, 'ig_username'),
            'display_name' => data_get($pageData, 'ig_name', data_get($pageData, 'ig_username')),
            'avatar_url' => $avatarPath,
            'access_token' => data_get($pageData, 'page_access_token'),
            'refresh_token' => null,
            'token_expires_at' => null,
            'scopes' => $this->scopes,
            'meta' => [
                'page_id' => data_get($pageData, 'page_id'),
                'page_name' => data_get($pageData, 'page_name'),
            ],
        ];

        if ($existingAccount) {
            $existingAccount->update($accountData);
            $existingAccount->markAsConnected();

            session()->forget('social_reconnect_id');

            return $this->popupCallback(true, 'Instagram account reconnected!', $this->platform->value);
        }

        $account = $workspace->socialAccounts()->create(array_merge($accountData, [
            'platform' => $this->platform->value,
            'status' => Status::Connected,
        ]));

        $isOnboarding = session('social_connect_onboarding', false);

        return $this->popupCallback(true, 'Instagram account connected!', $this->platform->value, $isOnboarding);
    }

    private function fetchPagesWithInstagram(string $userToken): array
    {
        try {
            $response = Http::get('https://graph.facebook.com/v20.0/me/accounts', [
                'access_token' => $userToken,
                'fields' => 'id,name,username,picture{url},access_token,instagram_business_account',
            ]);

            if ($response->failed()) {
                Log::error('Instagram via Facebook pages fetch failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $pages = data_get($response->json(), 'data', []);
            $results = [];

            foreach ($pages as $page) {
                $igAccountId = data_get($page, 'instagram_business_account.id');

                if (! $igAccountId) {
                    continue;
                }

                // Fetch IG account details
                $igResponse = Http::get("https://graph.facebook.com/v20.0/{$igAccountId}", [
                    'access_token' => data_get($page, 'access_token'),
                    'fields' => 'username,name,profile_picture_url',
                ]);

                $igData = $igResponse->successful() ? $igResponse->json() : [];

                $results[] = [
                    'page_id' => data_get($page, 'id'),
                    'page_name' => data_get($page, 'name'),
                    'page_picture' => data_get($page, 'picture.data.url'),
                    'page_access_token' => data_get($page, 'access_token'),
                    'ig_id' => $igAccountId,
                    'ig_username' => data_get($igData, 'username'),
                    'ig_name' => data_get($igData, 'name'),
                    'ig_picture' => data_get($igData, 'profile_picture_url'),
                ];
            }

            return $results;
        } catch (\Exception $e) {
            Log::error('Instagram via Facebook pages fetch error', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
