<?php

declare(strict_types=1);

namespace App\Http\Middleware\Mcp;

use App\Models\ApiToken;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMcpToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Missing API key.'], Response::HTTP_UNAUTHORIZED);
        }

        if (! str_starts_with($token, 'tp_') || strlen($token) !== 51) {
            return response()->json(['message' => 'Invalid API key.'], Response::HTTP_UNAUTHORIZED);
        }

        $lookup = substr($token, 3, 16);
        $apiToken = ApiToken::where('token_lookup', $lookup)->first();

        if (! $apiToken || ! Hash::check($token, $apiToken->token_hash)) {
            return response()->json(['message' => 'Invalid API key.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($apiToken->status === 'expired') {
            return response()->json(['message' => 'API key has expired.'], Response::HTTP_UNAUTHORIZED);
        }

        $apiToken->update(['last_used_at' => now()]);

        $workspace = $apiToken->workspace;
        $user = $workspace->owner;

        if (! $user) {
            return response()->json(['message' => 'No workspace owner found.'], Response::HTTP_UNAUTHORIZED);
        }

        if (! config('trypost.self_hosted') && ! $this->hasActiveSubscription($workspace)) {
            return response()->json(['message' => 'Active subscription required.'], Response::HTTP_PAYMENT_REQUIRED);
        }

        $user->current_workspace_id = $workspace->id;
        Auth::login($user);

        return $next($request);
    }

    private function hasActiveSubscription(Workspace $workspace): bool
    {
        $owner = $workspace->owner;

        if (! $owner) {
            return false;
        }

        return $owner->subscribed('default') || $owner->onTrial('default');
    }
}
