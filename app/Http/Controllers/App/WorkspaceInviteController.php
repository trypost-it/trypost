<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Invite\CreateInvite;
use App\Actions\Invite\DeleteInvite;
use App\Actions\Invite\RemoveMember;
use App\Enums\UserWorkspace\Role as WorkspaceRole;
use App\Http\Requests\App\Invite\StoreWorkspaceInviteRequest;
use App\Models\WorkspaceInvite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceInviteController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageTeam', $workspace);

        return Inertia::render('settings/Members', [
            'workspace' => $workspace,
            'invites' => $workspace->invites()
                ->latest()
                ->get(),
            'members' => $workspace->members()
                ->wherePivot('role', '!=', WorkspaceRole::Owner->value)
                ->get()
                ->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->pivot->role,
                ]),
            'owner' => [
                'id' => $workspace->owner->id,
                'name' => $workspace->owner->name,
                'email' => $workspace->owner->email,
                'role' => WorkspaceRole::Owner->value,
            ],
            'roles' => collect(WorkspaceRole::cases())
                ->filter(fn ($role) => $role !== WorkspaceRole::Owner)
                ->map(fn ($role) => [
                    'value' => $role->value,
                    'label' => $role->label(),
                ])->values(),
        ]);
    }

    public function store(StoreWorkspaceInviteRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageTeam', $workspace);

        $existingInvite = $workspace->invites()
            ->where('email', $request->email)
            ->first();

        if ($existingInvite) {
            return back()->withErrors([
                'email' => 'An invite already exists for this email.',
            ]);
        }

        if ($workspace->members()->where('email', $request->email)->exists()) {
            return back()->withErrors([
                'email' => 'This user is already a member of the workspace.',
            ]);
        }

        CreateInvite::execute($workspace, $request->validated());

        session()->flash('flash.banner', __('settings.members.flash.invite_sent'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function destroy(Request $request, WorkspaceInvite $invite): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageTeam', $workspace);

        if ($invite->workspace_id !== $workspace->id) {
            abort(404);
        }

        DeleteInvite::execute($invite);

        session()->flash('flash.banner', __('settings.members.flash.invite_deleted'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function removeMember(Request $request, string $userId): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageTeam', $workspace);

        $memberPivot = $workspace->members()->where('user_id', $userId)->first()?->pivot;

        if ($memberPivot && $memberPivot->role === WorkspaceRole::Owner->value) {
            return back()->withErrors(['member' => 'Cannot remove the workspace owner.']);
        }

        RemoveMember::execute($workspace, $userId);

        session()->flash('flash.banner', __('settings.members.flash.member_removed'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function updateRole(Request $request, string $userId): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageTeam', $workspace);

        $memberPivot = $workspace->members()->where('user_id', $userId)->first()?->pivot;

        if ($memberPivot && $memberPivot->role === WorkspaceRole::Owner->value) {
            return back()->withErrors(['role' => 'Cannot change the workspace owner role.']);
        }

        $validated = $request->validate([
            'role' => ['required', Rule::in([WorkspaceRole::Admin->value, WorkspaceRole::Member->value])],
        ]);

        $workspace->members()->updateExistingPivot($userId, [
            'role' => data_get($validated, 'role'),
        ]);

        session()->flash('flash.banner', __('settings.members.flash.role_updated'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }
}
