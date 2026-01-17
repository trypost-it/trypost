<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Http\Requests\StoreWorkspaceInviteRequest;
use App\Models\WorkspaceInvite;
use App\Notifications\WorkspaceInviteNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return Inertia::render('workspaces/Invites', [
            'workspace' => $workspace,
            'invites' => $workspace->invites()
                ->with('inviter')
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
            ->pending()
            ->first();

        if ($existingInvite) {
            return back()->withErrors([
                'email' => 'A pending invite already exists for this email.',
            ]);
        }

        if ($workspace->members()->where('email', $request->email)->exists()) {
            return back()->withErrors([
                'email' => 'This user is already a member of the workspace.',
            ]);
        }

        $invite = $workspace->invites()->create([
            'invited_by' => $request->user()->id,
            'email' => $request->email,
            'role' => $request->role ?? WorkspaceRole::Member,
        ]);

        $invite->notify(new WorkspaceInviteNotification($invite));

        return back()->with('success', 'Invite sent successfully!');
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

        $invite->cancel();

        return back()->with('success', 'Invite cancelled.');
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invite = WorkspaceInvite::where('token', $token)->firstOrFail();

        if (! $invite->isValid()) {
            if ($invite->isExpired()) {
                return redirect()->route('dashboard')
                    ->withErrors(['invite' => 'This invite has expired.']);
            }

            return redirect()->route('dashboard')
                ->withErrors(['invite' => 'This invite is no longer valid.']);
        }

        $user = $request->user();

        if (! $user) {
            session(['pending_invite_token' => $token]);

            return redirect()->route('login')
                ->with('message', 'Please log in to accept the invite.');
        }

        if ($invite->workspace->hasMember($user)) {
            // Switch to this workspace
            $user->switchWorkspace($invite->workspace);

            return redirect()->route('calendar')
                ->with('message', 'You are already a member of this workspace.');
        }

        $invite->accept($user);

        // Switch to the new workspace
        $user->switchWorkspace($invite->workspace);

        return redirect()->route('calendar')
            ->with('success', 'You are now a member of the workspace!');
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

        return back()->with('success', 'Member removed successfully.');
    }
}
