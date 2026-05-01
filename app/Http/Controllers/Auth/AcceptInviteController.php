<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserWorkspace\Role;
use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInviteController extends Controller
{
    /**
     * Display the invite view.
     */
    public function show(Invite $invite): Response
    {
        $invite->load('account');

        $firstWorkspaceId = collect($invite->workspaces ?? [])->first();
        $workspace = $firstWorkspaceId ? Workspace::find($firstWorkspaceId) : null;

        $role = $invite->role ?? Role::Member;

        return Inertia::render('auth/AcceptInvite', [
            'invite' => [
                'id' => $invite->id,
                'email' => $invite->email,
                'account' => [
                    'id' => $invite->account->id,
                    'name' => $invite->account->name,
                ],
                'workspace' => $workspace ? [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                ] : null,
                'role' => [
                    'value' => $role->value,
                    'label' => $role->label(),
                ],
            ],
        ]);
    }

    /**
     * Accept the invite.
     */
    public function accept(Request $request, Invite $invite): RedirectResponse
    {
        $user = $request->user();

        // Verify the invite is for this user
        if ($invite->email !== $user->email) {
            session()->flash('flash.banner', __('settings.members.flash.wrong_email'));
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('app.calendar');
        }

        // Check if already a member of the account
        if ($user->account_id === $invite->account_id) {
            $invite->update(['accepted_at' => now()]);

            session()->flash('flash.banner', __('settings.members.flash.already_member'));
            session()->flash('flash.bannerStyle', 'info');

            return redirect()->route('app.calendar');
        }

        // Add user to the account
        $user->update(['account_id' => $invite->account_id]);

        // Attach user to the invited workspaces
        if ($invite->workspaces) {
            foreach ($invite->workspaces as $workspaceId) {
                $workspace = Workspace::find($workspaceId);

                if ($workspace && $workspace->account_id === $invite->account_id) {
                    $workspace->members()->syncWithoutDetaching([
                        $user->id => ['role' => Role::Member->value],
                    ]);

                    // Set first workspace as current
                    if (! $user->current_workspace_id) {
                        $user->update(['current_workspace_id' => $workspace->id]);
                    }
                }
            }
        }

        $invite->update(['accepted_at' => now()]);

        session()->flash('flash.banner', __('settings.members.flash.invite_accepted'));
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('app.calendar');
    }

    /**
     * Decline the invite.
     */
    public function decline(Request $request, Invite $invite): RedirectResponse
    {
        $user = $request->user();

        // Verify the invite is for this user
        if ($invite->email !== $user->email) {
            session()->flash('flash.banner', __('settings.members.flash.wrong_email'));
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('app.calendar');
        }

        $invite->delete();

        session()->flash('flash.banner', __('settings.members.flash.invite_declined'));
        session()->flash('flash.bannerStyle', 'info');

        return redirect()->route('app.calendar');
    }
}
