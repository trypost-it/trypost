<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialPlatform;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class TikTokController extends SocialController
{
    protected string $driver = 'tiktok';

    protected SocialPlatform $platform = SocialPlatform::TikTok;

    protected array $scopes = [
        'user.info.basic',
        'user.info.profile',
        'video.publish',
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
            $socialUser = Socialite::driver($this->driver)
                ->scopes($this->scopes)
                ->user();

            Log::info('TikTok OAuth User Data', [
                'nickname' => $socialUser->getNickname(),
                'user' => $socialUser->user ?? [],
                'attributes' => $socialUser->attributes ?? [],
            ]);

            // TikTok returns username via getNickname() when user.info.profile scope is included
            $username = $socialUser->getNickname();
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
            Log::error('TikTok OAuth Error', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('workspaces.accounts', $workspace)
                ->with('error', 'Erro ao conectar conta. Tente novamente.');
        }
    }
}
