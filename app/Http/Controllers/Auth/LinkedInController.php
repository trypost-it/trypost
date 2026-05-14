<?php

declare(strict_types=1);

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
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class LinkedInController extends SocialController
{
    protected string $driver = 'linkedin';

    protected SocialPlatform $platform = SocialPlatform::LinkedIn;

    protected array $scopes = [
        'openid',
        'profile',
        'email',
        'r_basicprofile',
        'w_member_social',
    ];

    public function connect(Request $request): Response|RedirectResponse
    {
        $this->ensurePlatformEnabled();

        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

        return $this->redirectToProvider($request, $this->driver, $this->scopes);
    }

    public function callback(Request $request): View
    {
        $workspaceId = session('social_connect_workspace');

        if (! $workspaceId) {
            return $this->popupCallback(false, __('accounts.popup_callback.session_expired'), $this->platform->value);
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            return $this->popupCallback(false, __('accounts.popup_callback.workspace_not_found'), $this->platform->value);
        }

        try {
            $socialUser = Socialite::driver($this->driver)->user();

            // Fetch vanityName from LinkedIn API (not available via OpenID)
            $username = $this->fetchVanityName($socialUser->token);

            Log::info('LinkedIn OAuth User Data', [
                'nickname' => $socialUser->getNickname(),
                'username' => $username,
                'user' => $socialUser->user ?? [],
            ]);

            $avatarPath = uploadFromUrl($socialUser->getAvatar());

            $account = $workspace->socialAccounts()->updateOrCreate(
                [
                    'platform' => $this->platform->value,
                    'platform_user_id' => $socialUser->getId(),
                ],
                [
                    'username' => $username,
                    'display_name' => $socialUser->getName(),
                    'avatar_url' => $avatarPath,
                    'access_token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                    'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                    // LinkedIn returns scope CSV-joined but Socialite splits on space, so re-split here.
                    'scopes' => explode(',', implode(',', $socialUser->approvedScopes)),
                    'status' => Status::Connected,
                    'error_message' => null,
                    'disconnected_at' => null,
                ],
            );

            // Sync tokens to LinkedIn Page if it exists
            app(LinkedInTokenSynchronizer::class)->syncTokens($account);

            return $this->popupCallback(true, __('accounts.popup_callback.connected'), $this->platform->value);
        } catch (\Exception $e) {
            Log::error('LinkedIn OAuth Error', [
                'error' => $e->getMessage(),
            ]);

            return $this->popupCallback(false, __('accounts.popup_callback.error_connecting'), $this->platform->value);
        }
    }

    private function fetchVanityName(string $accessToken): ?string
    {
        try {
            $response = Http::withToken($accessToken)
                ->withHeaders(['X-RestLi-Protocol-Version' => '2.0.0'])
                ->get(config('trypost.platforms.linkedin.api').'/v2/me', [
                    'projection' => '(id,vanityName,localizedFirstName,localizedLastName)',
                ]);

            Log::info('LinkedIn /me API response', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            if ($response->successful()) {
                return $response->json('vanityName');
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch LinkedIn vanityName', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
