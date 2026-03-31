<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\User\CreateUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialLoginController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google-auth')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google-auth')->user();
        } catch (\Exception) {
            return redirect()->route('login');
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            return $this->loginExistingUser($user);
        }

        return $this->registerNewUser($googleUser);
    }

    private function loginExistingUser(User $user): RedirectResponse
    {
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Auth::login($user, remember: true);

        return redirect()->route('app.home');
    }

    private function registerNewUser(\Laravel\Socialite\Contracts\User $googleUser): RedirectResponse
    {
        $user = CreateUser::execute([
            'name' => $googleUser->getName(),
            'email' => $googleUser->getEmail(),
            'email_verified_at' => now(),
        ]);

        event(new Registered($user));

        Auth::login($user, remember: true);

        session()->flash('auth_provider', 'google');

        return redirect()->route('register.success');
    }
}
