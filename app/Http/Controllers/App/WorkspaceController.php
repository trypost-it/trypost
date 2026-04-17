<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Ai\AutofillBrand;
use App\Actions\Workspace\CreateWorkspace;
use App\Actions\Workspace\DeleteWorkspace;
use App\Http\Requests\App\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\App\Workspace\UpdateWorkspaceRequest;
use App\Models\Workspace;
use App\Services\Brand\LogoAttacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class WorkspaceController extends Controller
{
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

        $workspace = $user->currentWorkspace;

        if ($user->ownedWorkspacesCount() > 0 && ! $user->account?->hasActiveSubscription()) {
            return redirect()->route('app.billing.index')
                ->with('message', 'Subscribe to create more workspaces.');
        }

        return Inertia::render('workspaces/Create');
    }

    public function autofillBrand(Request $request, AutofillBrand $autofill): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:255'],
        ]);

        try {
            $metadata = $autofill(data_get($validated, 'url'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json($metadata->toArray());
    }

    public function store(StoreWorkspaceRequest $request, LogoAttacher $logoAttacher): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validated();
        $isFirstWorkspace = ! $user->workspaces()->exists();

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

        return $isFirstWorkspace
            ? redirect()->route('app.accounts')->with('success', __('workspaces.create.first_workspace_success'))
            : redirect()->route('app.calendar')->with('success', __('workspaces.create.success'));
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

        $members = $workspace->members()
            ->get()
            ->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
                'is_owner' => $member->id === $workspace->account?->owner_id,
            ]);

        $invitations = $workspace->invites()
            ->select('id', 'email')
            ->latest()
            ->get();

        return Inertia::render('settings/Workspace', [
            'workspace' => $workspace,
            'members' => $members,
            'invitations' => $invitations,
        ]);
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('update', $workspace);

        $request->validate([
            'photo' => ['required', 'image', 'max:2048'],
        ]);

        $workspace->clearMediaCollection('logo');
        $workspace->addMedia($request->file('photo'), 'logo');
        $workspace->unsetRelation('media');

        session()->flash('flash.banner', __('settings.flash.logo_updated'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function deleteLogo(Request $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('update', $workspace);

        $workspace->clearMediaCollection('logo');
        $workspace->unsetRelation('media');

        session()->flash('flash.banner', __('settings.flash.logo_deleted'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
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

        session()->flash('flash.banner', __('settings.flash.workspace_updated'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('app.workspace.settings');
    }

    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('delete', $workspace);

        $user = $request->user();

        DeleteWorkspace::execute($user, $workspace);

        return redirect()->route('app.workspaces.index')
            ->with('success', 'Workspace deleted successfully!');
    }
}
