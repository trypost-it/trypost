<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Enums\UserWorkspace\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\Settings\ProfileDeleteRequest;
use App\Http\Requests\App\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        session()->flash('flash.banner', __('settings.flash.profile_updated'));
        session()->flash('flash.bannerStyle', 'success');

        return to_route('app.profile.edit');
    }

    public function uploadPhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:2048'],
        ]);

        $user = $request->user();
        $user->clearMediaCollection('avatar');
        $user->addMedia($request->file('photo'), 'avatar');
        $user->unsetRelation('media');

        session()->flash('flash.banner', __('settings.flash.photo_updated'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function deletePhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->clearMediaCollection('avatar');
        $user->unsetRelation('media');

        session()->flash('flash.banner', __('settings.flash.photo_deleted'));
        session()->flash('flash.bannerStyle', 'success');

        return back();
    }

    public function updateLanguage(Request $request): RedirectResponse
    {
        $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', array_keys(config('languages.available')))],
        ]);

        session()->flash('flash.banner', __('settings.flash.language_updated'));
        session()->flash('flash.bannerStyle', 'success');

        return back()->withCookie(
            cookie()->forever('locale', $request->locale, '/', config('session.domain'))
        );
    }

    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user) {
            if ($user->subscribed('default')) {
                $user->subscription('default')->cancelNow();
            }
            $user->subscriptions()->delete();
            $user->update(['current_workspace_id' => null]);

            $ownedWorkspaces = $user->workspaces()->wherePivot('role', Role::Owner->value)->get();

            foreach ($ownedWorkspaces as $workspace) {
                foreach ($workspace->members as $member) {
                    if ($member->id !== $user->id && $member->current_workspace_id === $workspace->id) {
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

            $user->workspaces()->detach();
        });

        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
