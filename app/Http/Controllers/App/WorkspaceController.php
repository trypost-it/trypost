<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Ai\AutofillBrand;
use App\Actions\Workspace\CreateWorkspace;
use App\Actions\Workspace\DeleteWorkspace;
use App\Actions\Workspace\WorkspaceDeletionImpact;
use App\Enums\Workspace\BrandFont;
use App\Enums\Workspace\ImageStyle;
use App\Http\Requests\App\Workspace\AutofillBrandRequest;
use App\Http\Requests\App\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\App\Workspace\UpdateWorkspaceRequest;
use App\Http\Requests\App\Workspace\UploadLogoRequest;
use App\Http\Resources\App\WorkspaceMemberResource;
use App\Models\Account;
use App\Models\Workspace;
use App\Services\Brand\LogoAttacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class WorkspaceController extends Controller
{
    public function searchMembers(Request $request): AnonymousResourceCollection
    {
        $workspace = $request->user()->currentWorkspace;

        abort_if(! $workspace, SymfonyResponse::HTTP_FORBIDDEN);

        $this->authorize('view', $workspace);

        $term = trim((string) $request->input('q', ''));

        $members = $workspace->members()
            ->where('users.id', '!=', $request->user()->id)
            ->when($term !== '', fn ($query) => $query->where('users.name', 'ilike', '%'.$term.'%'))
            ->orderBy('users.name')
            ->limit(50)
            ->get(['users.id', 'users.name', 'users.email']);

        return WorkspaceMemberResource::collection($members);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $workspaces = $user->workspaces()
            ->with('media')
            ->withCount(['socialAccounts', 'posts'])
            ->latest()
            ->get();

        return Inertia::render('workspaces/Index', [
            'workspaces' => $workspaces,
            'currentWorkspaceId' => $user->current_workspace_id,
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->ownedWorkspacesCount() > 0 && ! $user->account?->hasActiveSubscription()) {
            return redirect()->route('app.billing.index')
                ->with('message', 'Subscribe to create more workspaces.');
        }

        if ($this->hasReachedWorkspaceLimit($user->account)) {
            return back()->with('flash.error', __('workspaces.limit_reached'));
        }

        return Inertia::render('workspaces/Create', [
            'availableFonts' => BrandFont::values(),
            'availableImageStyles' => ImageStyle::values(),
        ]);
    }

    public function autofillBrand(AutofillBrandRequest $request, AutofillBrand $autofill): JsonResponse
    {
        $validated = $request->validated();

        $url = data_get($validated, 'url');

        $cacheKey = 'brand-analyzer:'.md5($url);
        if ($cached = Cache::get($cacheKey)) {
            return response()->json($cached);
        }

        $rateLimitKey = "brand-analyzer:{$request->ip()}";
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return response()->json(
                ['message' => __('billing.flash.brand_analyzer_rate_limited')],
                SymfonyResponse::HTTP_TOO_MANY_REQUESTS,
            );
        }
        RateLimiter::hit($rateLimitKey, 86400);

        $user = $request->user();

        try {
            $metadata = $autofill($url, $user->currentWorkspace, $user->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $metadata->toArray();

        Cache::put($cacheKey, $result, now()->addDays(7));

        return response()->json($result);
    }

    public function store(StoreWorkspaceRequest $request, LogoAttacher $logoAttacher): RedirectResponse
    {
        $user = $request->user();

        if ($this->hasReachedWorkspaceLimit($user->account)) {
            abort(SymfonyResponse::HTTP_FORBIDDEN, __('workspaces.limit_reached'));
        }

        $validated = $request->validated();

        $workspace = CreateWorkspace::execute($user, $validated);

        if ($logoUrl = data_get($validated, 'logo_url')) {
            try {
                $logoAttacher->attach($workspace, $logoUrl);
            } catch (Throwable $e) {
                Log::warning('Logo attach failed during workspace creation', [
                    'workspace_id' => $workspace->id,
                    'logo_url' => $logoUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('app.accounts', ['openDialog' => 'true'])
            ->with('success', __('workspaces.create.success'));
    }

    public function switch(Request $request, Workspace $workspace): RedirectResponse
    {
        $user = $request->user();

        if (! $user->belongsToWorkspace($workspace)) {
            abort(403);
        }

        $user->switchWorkspace($workspace);

        return redirect()->route('app.calendar');
    }

    public function settings(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('update', $workspace);

        return Inertia::render('settings/workspace/Workspace', [
            'workspace' => $workspace,
        ]);
    }

    public function brandSettings(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('update', $workspace);

        return Inertia::render('settings/workspace/Brand', [
            'workspace' => $workspace,
            'availableFonts' => BrandFont::values(),
            'availableImageStyles' => ImageStyle::values(),
        ]);
    }

    public function uploadLogo(UploadLogoRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('update', $workspace);

        $workspace->clearMediaCollection('logo');
        $workspace->addMedia($request->file('photo'), 'logo');
        $workspace->unsetRelation('media');

        return back()->with('flash.success', __('settings.flash.logo_updated'));
    }

    public function deleteLogo(Request $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('update', $workspace);

        $workspace->clearMediaCollection('logo');
        $workspace->unsetRelation('media');

        return back()->with('flash.success', __('settings.flash.logo_deleted'));
    }

    public function updateSettings(UpdateWorkspaceRequest $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('update', $workspace);

        $workspace->update($request->validated());

        return back()->with('flash.success', __('settings.flash.workspace_updated'));
    }

    public function deletionImpact(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('delete', $workspace);

        return response()->json(WorkspaceDeletionImpact::execute($workspace));
    }

    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('delete', $workspace);

        $user = $request->user();

        DeleteWorkspace::execute($user, $workspace);

        return redirect()->route('app.workspaces.index')
            ->with('flash.success', __('workspaces.flash.deleted'));
    }

    /**
     * Check whether the account has hit its plan's workspace limit.
     * Returns false in self-hosted mode (no plan limits apply).
     */
    private function hasReachedWorkspaceLimit(?Account $account): bool
    {
        if (config('trypost.self_hosted')) {
            return false;
        }

        if (! $account) {
            return false;
        }

        $limit = (int) ($account->plan?->workspace_limit ?? 1);

        return $account->workspaces()->count() >= $limit;
    }
}
