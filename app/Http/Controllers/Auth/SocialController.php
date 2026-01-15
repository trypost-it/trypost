<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SocialPlatform;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SocialController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        $this->authorize('view', $workspace);

        $connectedAccounts = $workspace->socialAccounts;

        $platforms = collect(SocialPlatform::cases())->map(function ($platform) use ($connectedAccounts) {
            $connected = $connectedAccounts->firstWhere('platform', $platform);

            return [
                'value' => $platform->value,
                'label' => $platform->label(),
                'color' => $platform->color(),
                'connected' => $connected !== null,
                'account' => $connected,
            ];
        });

        return Inertia::render('accounts/Index', [
            'workspace' => $workspace,
            'platforms' => $platforms,
        ]);
    }

    public function disconnect(Workspace $workspace, SocialAccount $account): RedirectResponse
    {
        $this->authorize('update', $workspace);

        if ($account->workspace_id !== $workspace->id) {
            abort(403);
        }

        $account->delete();

        return back()->with('success', 'Conta desconectada com sucesso!');
    }

    protected function redirectToProvider(Workspace $workspace, string $driver, array $scopes): SymfonyResponse
    {
        session(['social_connect_workspace' => $workspace->id]);

        return Inertia::location(
            Socialite::driver($driver)
                ->scopes($scopes)
                ->redirect()
                ->getTargetUrl()
        );
    }

    protected function handleCallback(
        Request $request,
        SocialPlatform $platform,
        string $driver
    ): RedirectResponse {
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

        if ($workspace->hasConnectedPlatform($platform->value)) {
            return redirect()->route('workspaces.accounts', $workspace)
                ->with('error', 'Esta plataforma já está conectada.');
        }

        try {
            $socialUser = Socialite::driver($driver)->user();
            $avatarPath = uploadFromUrl($socialUser->getAvatar());

            $workspace->socialAccounts()->create([
                'platform' => $platform->value,
                'platform_user_id' => $socialUser->getId(),
                'username' => $socialUser->getNickname(),
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
            Log::error('Social OAuth Error', [
                'platform' => $platform->value,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('workspaces.accounts', $workspace)
                ->with('error', 'Erro ao conectar conta. Tente novamente.');
        }
    }
}
