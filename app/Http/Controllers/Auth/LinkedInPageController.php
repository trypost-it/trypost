<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\Status;
use App\Models\Workspace;
use App\Services\Social\LinkedInTokenSynchronizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LinkedInPageController extends SocialController
{
    protected string $driver = 'linkedin-openid';

    protected SocialPlatform $platform = SocialPlatform::LinkedInPage;

    protected array $scopes = [
        'openid',
        'profile',
        'email',
        'w_organization_social',
        'r_organization_social',
        'rw_organization_admin',
        'w_member_social',
    ];

    public function connect(Request $request): SymfonyResponse|RedirectResponse
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
            'linkedin_page_reconnect_id' => $existingAccount?->id,
            'social_connect_onboarding' => $request->boolean('onboarding'),
        ]);

        return Inertia::location(
            Socialite::driver($this->driver)
                ->scopes($this->scopes)
                ->with([
                    'redirect_uri' => config('services.linkedin-openid.redirect_page'),
                ])
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

        try {
            $socialUser = Socialite::driver($this->driver)
                ->scopes($this->scopes)
                ->with([
                    'redirect_uri' => config('services.linkedin-openid.redirect_page'),
                ])
                ->user();

            // Fetch organizations the user is admin of
            $organizations = $this->fetchOrganizations($socialUser->token);

            if (empty($organizations)) {
                return $this->popupCallback(false, 'You are not an administrator of any LinkedIn page.', $this->platform->value);
            }

            // Store data in session and redirect to selection page
            session([
                'linkedin_page_pending' => [
                    'workspace_id' => $workspace->id,
                    'user_id' => $socialUser->getId(),
                    'name' => $socialUser->getName(),
                    'avatar' => $socialUser->getAvatar(),
                    'token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                    'expires_in' => $socialUser->expiresIn,
                    'organizations' => $organizations,
                    'reconnect_id' => session('linkedin_page_reconnect_id'),
                ],
            ]);

            return redirect()->route('social.linkedin-page.select-page');
        } catch (\Exception $e) {
            Log::error('LinkedIn Page OAuth Error', [
                'error' => $e->getMessage(),
            ]);

            return $this->popupCallback(false, 'Error connecting account. Please try again.', $this->platform->value);
        }
    }

    public function selectPage(Request $request): Response|RedirectResponse
    {
        $pendingData = session('linkedin_page_pending');

        if (! $pendingData) {
            session()->flash('flash.banner', __('accounts.flash.session_expired'));
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('accounts');
        }

        $workspace = Workspace::find($pendingData['workspace_id']);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            session()->flash('flash.banner', __('accounts.flash.workspace_not_found'));
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('accounts');
        }

        return Inertia::render('accounts/LinkedInPageSelect', [
            'workspace' => $workspace,
            'organizations' => $pendingData['organizations'],
        ]);
    }

    public function select(Request $request): View
    {
        $request->validate([
            'organization_id' => 'required',
            'organization_name' => 'required|string',
            'organization_vanity_name' => 'nullable|string',
            'organization_logo' => 'nullable|string',
        ]);

        $pendingData = session('linkedin_page_pending');

        if (! $pendingData) {
            return $this->popupCallback(false, 'Session expired. Please try again.', $this->platform->value);
        }

        $workspace = Workspace::find($pendingData['workspace_id']);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            return $this->popupCallback(false, 'Workspace not found.', $this->platform->value);
        }

        try {
            $avatarPath = uploadFromUrl($request->organization_logo);
            $reconnectId = $pendingData['reconnect_id'] ?? null;

            if ($reconnectId) {
                // Reconnect existing account
                $existingAccount = $workspace->socialAccounts()->find($reconnectId);

                if ($existingAccount) {
                    $existingAccount->update([
                        'platform_user_id' => $request->organization_id,
                        'username' => $request->organization_vanity_name,
                        'display_name' => $request->organization_name,
                        'avatar_url' => $avatarPath,
                        'access_token' => $pendingData['token'],
                        'refresh_token' => $pendingData['refresh_token'],
                        'token_expires_at' => $pendingData['expires_in'] ? now()->addSeconds($pendingData['expires_in']) : null,
                        'meta' => [
                            'organization_id' => $request->organization_id,
                            'admin_user_id' => $pendingData['user_id'],
                            'admin_name' => $pendingData['name'],
                        ],
                    ]);
                    $existingAccount->markAsConnected();

                    // Sync tokens to LinkedIn personal if it exists
                    app(LinkedInTokenSynchronizer::class)->syncTokens($existingAccount);

                    session()->forget(['linkedin_page_pending', 'linkedin_page_reconnect_id']);

                    return $this->popupCallback(true, 'LinkedIn Page reconnected!', $this->platform->value);
                }
            }

            // Create new account
            $account = $workspace->socialAccounts()->create([
                'platform' => $this->platform->value,
                'platform_user_id' => $request->organization_id,
                'username' => $request->organization_vanity_name,
                'display_name' => $request->organization_name,
                'avatar_url' => $avatarPath,
                'access_token' => $pendingData['token'],
                'refresh_token' => $pendingData['refresh_token'],
                'token_expires_at' => $pendingData['expires_in'] ? now()->addSeconds($pendingData['expires_in']) : null,
                'status' => Status::Connected,
                'meta' => [
                    'organization_id' => $request->organization_id,
                    'admin_user_id' => $pendingData['user_id'],
                    'admin_name' => $pendingData['name'],
                ],
            ]);

            // Sync tokens to LinkedIn personal if it exists
            app(LinkedInTokenSynchronizer::class)->syncTokens($account);

            session()->forget(['linkedin_page_pending', 'linkedin_page_reconnect_id']);

            return $this->popupCallback(true, 'LinkedIn Page connected!', $this->platform->value);
        } catch (\Exception $e) {
            Log::error('LinkedIn Page selection error', [
                'error' => $e->getMessage(),
            ]);

            return $this->popupCallback(false, 'Error connecting page. Please try again.', $this->platform->value);
        }
    }

    private function fetchOrganizations(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get('https://api.linkedin.com/v2/organizationAcls', [
                'q' => 'roleAssignee',
                'role' => 'ADMINISTRATOR',
                'projection' => '(elements*(organization~(id,localizedName,vanityName,logoV2(original~:playableStreams))))',
            ]);

        if ($response->failed()) {
            Log::error('LinkedIn Organizations fetch error', [
                'error' => $response->body(),
            ]);

            return [];
        }

        $data = $response->json();
        $organizations = [];

        foreach ($data['elements'] ?? [] as $element) {
            $org = $element['organization~'] ?? null;
            if ($org) {
                $logoUrl = null;
                if (isset($org['logoV2']['original~']['elements'][0]['identifiers'][0]['identifier'])) {
                    $logoUrl = $org['logoV2']['original~']['elements'][0]['identifiers'][0]['identifier'];
                }

                $organizations[] = [
                    'id' => $org['id'],
                    'name' => $org['localizedName'] ?? 'Unknown',
                    'vanity_name' => $org['vanityName'] ?? null,
                    'logo' => $logoUrl,
                ];
            }
        }

        return $organizations;
    }
}
