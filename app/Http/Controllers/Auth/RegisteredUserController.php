<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\User\Setup;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Register', [
            'email' => $request->query('email'),
            'redirect' => $request->query('redirect'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', Rules\Password::defaults()],
            'timezone' => ['nullable', 'string', 'timezone'],
        ]);

        // Check if registering via invite link (redirect contains /invites/)
        $isInviteRegistration = str_contains($request->input('redirect', ''), '/invites/');

        $user = DB::transaction(function () use ($request, $isInviteRegistration) {
            $defaultLanguage = Language::where('code', 'en-US')->first();

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'setup' => $isInviteRegistration ? Setup::Completed : Setup::Role,
                'language_id' => $defaultLanguage?->id,
                'email_verified_at' => $isInviteRegistration ? now() : null,
            ]);

            // Create default workspace for new user
            $workspace = Workspace::create([
                'user_id' => $user->id,
                'name' => $user->name."'s Workspace",
                'timezone' => $request->input('timezone', 'UTC'),
            ]);

            // Add user as owner member
            $workspace->members()->attach($user->id, ['role' => 'owner']);

            // Set as current workspace
            $user->update(['current_workspace_id' => $workspace->id]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        // Check for redirect param
        if ($redirect = $request->input('redirect')) {
            return redirect($redirect);
        }

        return redirect()->route('onboarding.step1');
    }
}
