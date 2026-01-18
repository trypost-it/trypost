<?php

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

    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $invite = WorkspaceInvite::where('token', $token)
            ->with(['workspace', 'inviter'])
            ->firstOrFail();

        if (! $invite->isPending()) {
            session()->flash('flash.banner', 'This invite is no longer valid.');
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('login');
        }

        $user = $request->user();

        // Store token in session for after login/register
        session(['pending_invite_token' => $token]);

        return Inertia::render('invites/Accept', [
            'invite' => [
                'id' => $invite->id,
                'token' => $invite->token,
                'email' => $invite->email,
                'role' => [
                    'value' => $invite->role->value,
                    'label' => $invite->role->label(),
                ],
                'workspace' => [
                    'id' => $invite->workspace->id,
                    'name' => $invite->workspace->name,
                ],
                'inviter' => [
                    'id' => $invite->inviter->id,
                    'name' => $invite->inviter->name,
                    'email' => $invite->inviter->email,
                ],
            ],
            'isAuthenticated' => (bool) $user,
            'userEmail' => $user?->email,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invite = WorkspaceInvite::where('token', $token)->firstOrFail();

        if (! $invite->isPending()) {
            session()->flash('flash.banner', 'This invite is no longer valid.');
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('login');
        }

        $user = $request->user();

        if (! $user) {
            session(['pending_invite_token' => $token]);

            return redirect()->route('login');
        }

        // Clear the pending token
        session()->forget('pending_invite_token');

        if ($invite->workspace->hasMember($user)) {
            $user->switchWorkspace($invite->workspace);

            session()->flash('flash.banner', 'You are already a member of this workspace.');
            session()->flash('flash.bannerStyle', 'info');

            return redirect()->route('calendar');
        }

        $invite->accept($user);
        $user->switchWorkspace($invite->workspace);

        session()->flash('flash.banner', 'You are now a member of the workspace!');
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('calendar');
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
