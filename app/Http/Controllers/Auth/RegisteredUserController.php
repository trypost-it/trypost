<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\User\CreateUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\Timezone;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Register', [
            'email' => $request->query('email'),
            'redirect' => $request->query('redirect'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', Rules\Password::defaults()],
            'timezone' => ['nullable', 'string', new Timezone],
        ]);

        $isInviteRegistration = str_contains($request->input('redirect', ''), '/invites/');

        $user = CreateUser::execute([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'timezone' => $request->input('timezone', 'UTC'),
            'is_invite' => $isInviteRegistration,
        ]);

        event(new Registered($user));

        Auth::login($user);

        if ($redirect = $request->input('redirect')) {
            if (str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//')) {
                return redirect($redirect);
            }
        }

        return redirect()->route('app.onboarding.role');
    }
}
