<?php

namespace App\Http\Controllers;

use App\Enums\InviteStatus;
use App\Enums\WorkspaceRole;
use App\Http\Requests\StoreWorkspaceInviteRequest;
use App\Models\Workspace;
use App\Models\WorkspaceInvite;
use App\Notifications\WorkspaceInviteNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceInviteController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        $this->authorize('update', $workspace);

        return Inertia::render('workspaces/Invites', [
            'workspace' => $workspace,
            'invites' => $workspace->invites()
                ->with('inviter')
                ->latest()
                ->get(),
            'members' => $workspace->members()->get()->map(fn ($member) => [
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

    public function store(StoreWorkspaceInviteRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $existingInvite = $workspace->invites()
            ->where('email', $request->email)
            ->pending()
            ->first();

        if ($existingInvite) {
            return back()->withErrors([
                'email' => 'Já existe um convite pendente para este email.',
            ]);
        }

        if ($workspace->members()->where('email', $request->email)->exists()) {
            return back()->withErrors([
                'email' => 'Este usuário já é membro do workspace.',
            ]);
        }

        $invite = $workspace->invites()->create([
            'invited_by' => $request->user()->id,
            'email' => $request->email,
            'role' => $request->role ?? WorkspaceRole::Member,
        ]);

        $invite->notify(new WorkspaceInviteNotification($invite));

        return back()->with('success', 'Convite enviado com sucesso!');
    }

    public function destroy(Workspace $workspace, WorkspaceInvite $invite): RedirectResponse
    {
        $this->authorize('update', $workspace);

        if ($invite->workspace_id !== $workspace->id) {
            abort(404);
        }

        $invite->cancel();

        return back()->with('success', 'Convite cancelado.');
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invite = WorkspaceInvite::where('token', $token)->firstOrFail();

        if (! $invite->isValid()) {
            if ($invite->isExpired()) {
                return redirect()->route('workspaces.index')
                    ->withErrors(['invite' => 'Este convite expirou.']);
            }

            return redirect()->route('workspaces.index')
                ->withErrors(['invite' => 'Este convite não é mais válido.']);
        }

        $user = $request->user();

        if (! $user) {
            session(['pending_invite_token' => $token]);

            return redirect()->route('login')
                ->with('message', 'Faça login para aceitar o convite.');
        }

        if ($invite->workspace->hasMember($user)) {
            return redirect()->route('workspaces.show', $invite->workspace)
                ->with('message', 'Você já é membro deste workspace.');
        }

        $invite->accept($user);

        return redirect()->route('workspaces.show', $invite->workspace)
            ->with('success', 'Você agora é membro do workspace!');
    }

    public function removeMember(Workspace $workspace, string $userId): RedirectResponse
    {
        $this->authorize('update', $workspace);

        if ($workspace->user_id === $userId) {
            return back()->withErrors(['member' => 'Não é possível remover o dono do workspace.']);
        }

        $workspace->members()->detach($userId);

        return back()->with('success', 'Membro removido com sucesso.');
    }
}
