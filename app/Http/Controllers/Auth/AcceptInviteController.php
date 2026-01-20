<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceInvite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInviteController extends Controller
{
    /**
     * Display the invite view.
     */
    public function show(WorkspaceInvite $invite): Response
    {
        $invite->load('workspace');

        return Inertia::render('auth/AcceptInvite', [
            'invite' => [
                'id' => $invite->id,
                'email' => $invite->email,
                'role' => [
                    'value' => $invite->role->value,
                    'label' => $invite->role->label(),
                ],
                'workspace' => [
                    'id' => $invite->workspace->id,
                    'name' => $invite->workspace->name,
                ],
            ],
        ]);
    }

    /**
     * Accept the invite.
     */
    public function accept(Request $request, WorkspaceInvite $invite): RedirectResponse
    {
        $user = $request->user();

        // Verify the invite is for this user
        if ($invite->email !== $user->email) {
            session()->flash('flash.banner', 'This invite is for a different email address.');
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('calendar');
        }

        // Check if already a member
        if ($invite->workspace->hasMember($user)) {
            $invite->delete();

            session()->flash('flash.banner', 'You are already a member of this workspace.');
            session()->flash('flash.bannerStyle', 'info');

            return redirect()->route('calendar');
        }

        // Accept the invite
        $workspaceId = $invite->workspace_id;
        $invite->accept($user);
        $user->update(['current_workspace_id' => $workspaceId]);

        session()->flash('flash.banner', 'Welcome! You are now a member of the workspace.');
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('calendar');
    }

    /**
     * Decline the invite.
     */
    public function decline(Request $request, WorkspaceInvite $invite): RedirectResponse
    {
        $user = $request->user();

        // Verify the invite is for this user
        if ($invite->email !== $user->email) {
            session()->flash('flash.banner', 'This invite is for a different email address.');
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('calendar');
        }

        $invite->delete();

        session()->flash('flash.banner', 'Invite declined.');
        session()->flash('flash.bannerStyle', 'info');

        return redirect()->route('calendar');
    }
}
