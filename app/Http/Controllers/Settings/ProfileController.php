<?php

namespace App\Http\Controllers\Settings;

use App\Enums\UserWorkspace\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        session()->flash('flash.banner', __('settings.flash.profile_updated'));
        session()->flash('flash.bannerStyle', 'success');

        return to_route('profile.edit');
    }

    /**
     * Update the user's language.
     */
    public function updateLanguage(Request $request): RedirectResponse
    {
        $request->validate([
            'language_id' => ['required', 'exists:languages,id'],
        ]);

        $request->user()->update([
            'language_id' => $request->language_id,
        ]);

        // Refresh the user model to clear cached language relationship
        $request->user()->refresh();

        session()->flash('flash.banner', __('settings.flash.language_updated'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user) {
            // Cancel active Stripe subscription and delete subscription records
            if ($user->subscribed('default')) {
                $user->subscription('default')->cancelNow();
            }
            $user->subscriptions()->delete();

            // Clear current workspace reference
            $user->update(['current_workspace_id' => null]);

            // Delete all workspaces owned by the user
            $ownedWorkspaces = $user->workspaces()->wherePivot('role', Role::Owner->value)->get();

            foreach ($ownedWorkspaces as $workspace) {
                // Update members who have this as current_workspace_id
                foreach ($workspace->members as $member) {
                    if ($member->id !== $user->id && $member->current_workspace_id === $workspace->id) {
                        // Find another workspace for this member
                        $otherWorkspace = $member->workspaces()
                            ->where('workspaces.id', '!=', $workspace->id)
                            ->first();

                        $member->update(['current_workspace_id' => $otherWorkspace?->id]);
                    }
                }

                $workspace->posts()->delete();
                $workspace->socialAccounts()->delete();
                $workspace->hashtags()->delete();
                $workspace->labels()->delete();
                $workspace->invites()->delete();
                $workspace->members()->detach();
                $workspace->delete();
            }

            // Remove user from workspaces where they are a member
            $user->workspaces()->detach();
        });

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
