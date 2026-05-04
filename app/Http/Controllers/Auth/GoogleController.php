<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\User\CreateUser;
use App\Http\Controllers\Auth\Concerns\PreservesUtmParameters;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    use PreservesUtmParameters;

    public function redirect(Request $request): RedirectResponse
    {
        $this->storeUtmParameters($request);

        return Socialite::driver('google-auth')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google-auth')->user();
        } catch (\Exception) {
            return redirect()->route('login');
        }

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            return $this->loginExistingUser($user, $googleUser->getId());
        }

        return $this->registerNewUser($googleUser);
    }

    private function loginExistingUser(User $user, string $googleId): RedirectResponse
    {
        if (! $user->google_id) {
            $user->update(['google_id' => $googleId]);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Auth::login($user, remember: true);

        $this->retrieveUtmParameters();

        return redirect()->route('app.home');
    }

    private function registerNewUser(\Laravel\Socialite\Contracts\User $googleUser): RedirectResponse
    {
        $utmParameters = $this->retrieveUtmParameters();

        $user = CreateUser::execute([
            'name' => $googleUser->getName(),
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'email_verified_at' => now(),
            'registration_ip' => request()->ip(),
        ], $utmParameters);

        event(new Registered($user));

        Auth::login($user, remember: true);

        session()->flash('auth_provider', 'google');

        return redirect()->route('register.success', $utmParameters);
    }
}
