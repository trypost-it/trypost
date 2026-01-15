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
    public function index(Request $request): Response
    {
        $workspaces = $request->user()
            ->workspaces()
            ->withCount(['socialAccounts', 'posts'])
            ->latest()
            ->get();

        return Inertia::render('workspaces/Index', [
            'workspaces' => $workspaces,
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // First workspace is free, subsequent ones require subscription
        if ($user->ownedWorkspacesCount() > 0 && ! $user->hasActiveSubscription()) {
            return redirect()->route('billing.index')
                ->with('message', 'Assine para criar mais workspaces.');
        }

        return Inertia::render('workspaces/Create');
    }

    public function store(StoreWorkspaceRequest $request): RedirectResponse
    {
        $user = $request->user();

        // First workspace is free, subsequent ones require subscription
        if ($user->ownedWorkspacesCount() > 0 && ! $user->hasActiveSubscription()) {
            return redirect()->route('billing.index')
                ->with('message', 'Assine para criar mais workspaces.');
        }

        $workspace = $user->workspaces()->create($request->validated());

        $workspace->members()->attach($user->id, ['role' => 'owner']);

        // Increment subscription quantity if user has subscription
        if ($user->hasActiveSubscription()) {
            $user->incrementWorkspaceQuantity();
        }

        return redirect()->route('workspaces.show', $workspace)
            ->with('success', 'Workspace criado com sucesso!');
    }

    public function show(Request $request, Workspace $workspace): Response
    {
        $this->authorize('view', $workspace);

        $workspace->load(['socialAccounts', 'posts' => function ($query) {
            $query->latest()->take(5);
        }]);

        $stats = [
            'total_posts' => $workspace->posts()->count(),
            'scheduled_posts' => $workspace->posts()->scheduled()->count(),
            'published_posts' => $workspace->posts()->published()->count(),
            'connected_accounts' => $workspace->socialAccounts()->count(),
        ];

        return Inertia::render('workspaces/Show', [
            'workspace' => $workspace,
            'stats' => $stats,
        ]);
    }

    public function edit(Workspace $workspace): Response
    {
        $this->authorize('update', $workspace);

        return Inertia::render('workspaces/Edit', [
            'workspace' => $workspace,
        ]);
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $workspace->update($request->validated());

        return redirect()->route('workspaces.show', $workspace)
            ->with('success', 'Workspace atualizado com sucesso!');
    }

    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('delete', $workspace);

        $user = $request->user();

        $workspace->delete();

        // Decrement subscription quantity if user has subscription
        if ($user->hasActiveSubscription()) {
            $user->decrementWorkspaceQuantity();
        }

        return redirect()->route('workspaces.index')
            ->with('success', 'Workspace excluído com sucesso!');
    }
}
