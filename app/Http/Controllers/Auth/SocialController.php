<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\SocialAccount\ToggleSocialAccount;
use App\Enums\PostPlatform\Status as PostPlatformStatus;
use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\Status;
use App\Features\SocialAccountLimit;
use App\Http\Controllers\Controller;
use App\Http\Resources\App\SocialAccountResource;
use App\Models\SocialAccount;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SocialController extends Controller
{
    protected SocialPlatform $platform;

    protected function ensurePlatformEnabled(): void
    {
        if (isset($this->platform) && ! $this->platform->isEnabled()) {
            abort(SymfonyResponse::HTTP_FORBIDDEN, 'This platform is currently unavailable.');
        }
    }

    protected function ensureSocialAccountLimit(Workspace $workspace): void
    {
        if (config('trypost.self_hosted')) {
            return;
        }

        $limit = Feature::for($workspace->account)->value(SocialAccountLimit::class);

        if ($workspace->socialAccounts()->count() >= $limit) {
            abort(SymfonyResponse::HTTP_FORBIDDEN, __('accounts.limit_reached'));
        }
    }

    public function index(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('view', $workspace);

        $accounts = $workspace->socialAccounts()
            ->when(
                $request->input('search'),
                fn ($query, $search) => $query->where(function ($q) use ($search): void {
                    $q->where('display_name', 'ilike', "%{$search}%")
                        ->orWhere('username', 'ilike', "%{$search}%")
                        ->orWhere('platform', 'ilike', "%{$search}%");
                }),
            )
            ->orderBy('id')
            ->paginate(config('app.pagination.default'));

        $platforms = collect(SocialPlatform::enabled())->map(fn ($platform) => [
            'value' => $platform->value,
            'label' => $platform->label(),
            'color' => $platform->color(),
        ])->values();

        return Inertia::render('accounts/Index', [
            'workspace' => $workspace,
            'accounts' => Inertia::scroll(fn () => SocialAccountResource::collection($accounts)),
            'platforms' => $platforms,
            'filters' => [
                'search' => $request->input('search', ''),
            ],
        ]);
    }

    public function disconnect(Request $request, SocialAccount $account): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

        if ($account->workspace_id !== $workspace->id) {
            abort(403);
        }

        // Drop pending platform rows from drafts/scheduled posts so the account
        // disappears cleanly from their UI. Published/failed rows survive via the
        // FK's nullOnDelete cascade and keep their snapshot fields for history.
        $account->postPlatforms()
            ->where('status', PostPlatformStatus::Pending->value)
            ->delete();

        $account->delete();

        session()->flash('flash.banner', __('accounts.flash.disconnected'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function toggleActive(Request $request, SocialAccount $account): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

        if ($account->workspace_id !== $workspace->id) {
            abort(403);
        }

        ToggleSocialAccount::execute($account);

        $status = $account->is_active ? 'activated' : 'deactivated';
        session()->flash('flash.banner', __("accounts.flash.{$status}"));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    protected function redirectToProvider(Request $request, string $driver, array $scopes): SymfonyResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->ensureSocialAccountLimit($workspace);

        session(['social_connect_workspace' => $workspace->id]);
        session(['social_connect_onboarding' => $request->boolean('onboarding')]);

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
    ): View {
        $workspaceId = session('social_connect_workspace');

        if (! $workspaceId) {
            return $this->popupCallback(false, 'Session expired. Please try again.', $platform->value);
        }

        $workspace = Workspace::find($workspaceId);

        if (! $workspace || ! $request->user()->can('manageAccounts', $workspace)) {
            return $this->popupCallback(false, 'Workspace not found.', $platform->value);
        }

        try {
            $socialUser = Socialite::driver($driver)->user();

            $avatarPath = uploadFromUrl($socialUser->getAvatar());

            $workspace->socialAccounts()->updateOrCreate(
                [
                    'platform' => $platform->value,
                    'platform_user_id' => $socialUser->getId(),
                ],
                [
                    'username' => $socialUser->getNickname(),
                    'display_name' => $socialUser->getName(),
                    'avatar_url' => $avatarPath,
                    'access_token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                    'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                    'scopes' => $socialUser->approvedScopes ?? null,
                    'status' => Status::Connected,
                    'error_message' => null,
                    'disconnected_at' => null,
                ],
            );

            return $this->popupCallback(true, 'Account connected!', $platform->value);
        } catch (\Exception $e) {
            Log::error('Social OAuth Error', [
                'platform' => $platform->value,
                'error' => $e->getMessage(),
            ]);

            return $this->popupCallback(false, 'Error connecting account. Please try again.', $platform->value);
        }
    }

    protected function forgetSocialConnectSession(): void
    {
        session()->forget(['social_connect_workspace', 'social_connect_onboarding']);
    }

    protected function getRedirectRoute(): string
    {
        return session('social_connect_onboarding', false) ? 'onboarding.connect' : 'accounts';
    }

    /**
     * Return a view that closes the popup and notifies the parent window.
     */
    protected function popupCallback(bool $success, string $message, ?string $platform = null): View
    {
        $this->forgetSocialConnectSession();

        return view('auth.social-callback', [
            'success' => $success,
            'message' => $message,
            'platform' => $platform,
        ]);
    }
}
