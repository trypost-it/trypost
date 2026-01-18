<?php

namespace App\Http\Responses;

use App\Enums\User\Setup;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        $user = $request->user();

        // Check for pending invite token
        if ($token = session('pending_invite_token')) {
            return redirect()->route('invites.show', $token);
        }

        // Determine redirect based on setup status
        $redirect = match ($user->setup) {
            Setup::Completed => route('calendar'),
            Setup::Role => route('onboarding.step1'),
            Setup::Connections => route('onboarding.step2'),
            Setup::Subscription => route('onboarding.step2'),
            default => route('onboarding.step1'),
        };

        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended($redirect);
    }
}
