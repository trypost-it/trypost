<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialPlatform;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    public function connect(Request $request, Workspace $workspace): SymfonyResponse
    {
        $this->authorize('manageAccounts', $workspace);

        if ($workspace->hasConnectedPlatform($this->platform->value)) {
            return back()->with('error', 'This platform is already connected.');
        }

        session(['social_connect_workspace' => $workspace->id]);

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
                session()->forget('social_connect_workspace');

                return redirect()->route('workspaces.accounts', $workspace)
                    ->with('error', 'You are not an administrator of any LinkedIn page.');
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
                ],
            ]);

            return redirect()->route('social.linkedin-page.select-page');
        } catch (\Exception $e) {
            Log::error('LinkedIn Page OAuth Error', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('workspaces.accounts', $workspace)
                ->with('error', 'Error connecting account. Please try again.');
        }
    }

    public function selectPage(Request $request): Response|RedirectResponse
    {
        $pendingData = session('linkedin_page_pending');

        if (! $pendingData) {
            return redirect()->route('workspaces.index')
                ->with('error', 'Session expired. Please try again.');
        }

        $workspace = Workspace::find($pendingData['workspace_id']);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            return redirect()->route('workspaces.index')
                ->with('error', 'Workspace not found.');
        }

        return Inertia::render('accounts/LinkedInPageSelect', [
            'workspace' => $workspace,
            'organizations' => $pendingData['organizations'],
        ]);
    }

    public function select(Request $request): RedirectResponse
    {
        $request->validate([
            'organization_id' => 'required',
            'organization_name' => 'required|string',
            'organization_vanity_name' => 'nullable|string',
            'organization_logo' => 'nullable|string',
        ]);

        $pendingData = session('linkedin_page_pending');

        if (! $pendingData) {
            return redirect()->route('workspaces.index')
                ->with('error', 'Session expired. Please try again.');
        }

        $workspace = Workspace::find($pendingData['workspace_id']);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            return redirect()->route('workspaces.index')
                ->with('error', 'Workspace not found.');
        }

        try {
            $avatarPath = uploadFromUrl($request->organization_logo);

            $workspace->socialAccounts()->create([
                'platform' => $this->platform->value,
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

            session()->forget(['social_connect_workspace', 'linkedin_page_pending']);

            return redirect()->route('workspaces.accounts', $workspace)
                ->with('success', 'LinkedIn Page connected successfully!');
        } catch (\Exception $e) {
            Log::error('LinkedIn Page selection error', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('workspaces.accounts', $workspace)
                ->with('error', 'Error connecting page. Please try again.');
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
