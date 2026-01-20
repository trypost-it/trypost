<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserWorkspace\Role as WorkspaceRole;
use App\Http\Requests\StoreWorkspaceInviteRequest;
use App\Mail\WorkspaceInvite as WorkspaceInviteMail;
use App\Models\WorkspaceInvite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceInviteController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('manageTeam', $workspace);

        return Inertia::render('settings/Members', [
            'workspace' => $workspace,
            'invites' => $workspace->invites()
                ->latest()
                ->get(),
            'members' => $workspace->members()
                ->where('user_id', '!=', $workspace->user_id)
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
            return redirect()->route('workspaces.create');
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

        $invite = $workspace->invites()->create([
            'email' => $request->email,
            'role' => $request->role ?? WorkspaceRole::Member,
        ]);

        Mail::to($invite->email)->send(new WorkspaceInviteMail($invite));

        session()->flash('flash.banner', 'Invite sent successfully!');
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function destroy(Request $request, WorkspaceInvite $invite): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('manageTeam', $workspace);

        if ($invite->workspace_id !== $workspace->id) {
            abort(404);
        }

        $invite->delete();

        session()->flash('flash.banner', 'Invite deleted.');
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function removeMember(Request $request, string $userId): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        $this->authorize('manageTeam', $workspace);

        if ($workspace->user_id === $userId) {
            return back()->withErrors(['member' => 'Cannot remove the workspace owner.']);
        }

        $workspace->members()->detach($userId);

        session()->flash('flash.banner', 'Member removed successfully.');
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }
}
