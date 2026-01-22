<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkspaceRequest;
use App\Http\Requests\UpdateWorkspaceRequest;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    /**
     * List all workspaces.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $workspaces = $user->workspaces()
            ->withCount(['socialAccounts', 'posts'])
            ->latest()
            ->get();

        return Inertia::render('workspaces/Index', [
            'workspaces' => $workspaces,
            'currentWorkspaceId' => $user->current_workspace_id,
        ]);
    }

    /**
     * Show create workspace form.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // First workspace is free, subsequent ones require subscription
        if ($user->ownedWorkspacesCount() > 0 && ! $user->hasActiveSubscription()) {
            return redirect()->route('billing.index')
                ->with('message', 'Subscribe to create more workspaces.');
        }

        return Inertia::render('workspaces/Create');
    }

    /**
     * Store a new workspace.
     */
    public function store(StoreWorkspaceRequest $request): RedirectResponse
    {
        $user = $request->user();

        // First workspace is free, subsequent ones require subscription
        if ($user->ownedWorkspacesCount() > 0 && ! $user->hasActiveSubscription()) {
            return redirect()->route('billing.index')
                ->with('message', 'Subscribe to create more workspaces.');
        }

        $workspace = Workspace::create([
            'user_id' => $user->id,
            ...$request->validated(),
            'timezone' => config('app.timezone', 'UTC'),
        ]);

        // Add user as owner member
        $workspace->members()->attach($user->id, ['role' => 'owner']);

        // Set as current workspace
        $user->switchWorkspace($workspace);

        // Increment subscription quantity if user has subscription
        if ($user->hasActiveSubscription()) {
            $user->incrementWorkspaceQuantity();
        }

        return redirect()->route('calendar')
            ->with('success', 'Workspace created successfully!');
    }

    /**
     * Switch to a different workspace.
     */
    public function switch(Request $request, Workspace $workspace): RedirectResponse
    {
        $user = $request->user();

        if (! $user->belongsToWorkspace($workspace)) {
            abort(403);
        }

        $user->switchWorkspace($workspace);

        return redirect()->route('calendar');
    }

    /**
     * Show workspace settings.
     */
    public function settings(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('update', $workspace);

        $timezones = collect(timezone_identifiers_list())
            ->mapWithKeys(fn ($tz) => [$tz => $tz])
            ->toArray();

        return Inertia::render('settings/Workspace', [
            'workspace' => $workspace,
            'timezones' => $timezones,
        ]);
    }

    /**
     * Update workspace settings.
     */
    public function updateSettings(UpdateWorkspaceRequest $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('update', $workspace);

        $workspace->update($request->validated());

        session()->flash('flash.banner', __('settings.flash.workspace_updated'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('workspace.settings');
    }

    /**
     * Delete a workspace.
     */
    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('delete', $workspace);

        $user = $request->user();

        // If deleting current workspace, clear it
        if ($user->current_workspace_id === $workspace->id) {
            $user->update(['current_workspace_id' => null]);
        }

        $workspace->delete();

        // Decrement subscription quantity if user has subscription
        if ($user->hasActiveSubscription()) {
            $user->decrementWorkspaceQuantity();
        }

        return redirect()->route('workspaces.index')
            ->with('success', 'Workspace deleted successfully!');
    }
}
