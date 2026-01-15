<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialPlatform;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    public function connect(Request $request, Workspace $workspace): Response
    {
        $this->authorize('update', $workspace);

        if ($workspace->hasConnectedPlatform($this->platform->value)) {
            return back()->with('error', 'Esta plataforma já está conectada.');
        }

        return $this->redirectToProvider($workspace, $this->driver, $this->scopes);
    }

    public function callback(Request $request): RedirectResponse
    {
        $workspaceId = session('social_connect_workspace');

        if (! $workspaceId) {
            return redirect()->route('workspaces.index')
                ->with('error', 'Sessão expirada. Tente novamente.');
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('update', $workspace)) {
            return redirect()->route('workspaces.index')
                ->with('error', 'Workspace não encontrado.');
        }

        if ($workspace->hasConnectedPlatform($this->platform->value)) {
            return redirect()->route('workspaces.accounts', $workspace)
                ->with('error', 'Esta plataforma já está conectada.');
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

            $workspace->socialAccounts()->create([
                'platform' => $this->platform->value,
                'platform_user_id' => $socialUser->getId(),
                'username' => $username,
                'display_name' => $socialUser->getName(),
                'avatar_url' => $avatarPath,
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                'scopes' => $socialUser->approvedScopes ?? null,
            ]);

            session()->forget('social_connect_workspace');

            return redirect()->route('workspaces.accounts', $workspace)
                ->with('success', 'Conta conectada com sucesso!');
        } catch (\Exception $e) {
            Log::error('LinkedIn OAuth Error', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('workspaces.accounts', $workspace)
                ->with('error', 'Erro ao conectar conta. Tente novamente.');
        }
    }

    private function fetchVanityName(string $accessToken): ?string
    {
        try {
            $response = Http::withToken($accessToken)
                ->withHeaders(['X-RestLi-Protocol-Version' => '2.0.0'])
                ->get('https://api.linkedin.com/v2/me', [
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
