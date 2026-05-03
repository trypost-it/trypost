<?php

declare(strict_types=1);

namespace App\Http\Middleware\Api;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LoadWorkspaceFromToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->token();

        if (! $token) {
            return response()->json(['message' => 'Token not found.'], Response::HTTP_UNAUTHORIZED);
        }

        $workspace = $token->workspace_id
            ? Workspace::find($token->workspace_id)
            : null;

        if (! $workspace) {
            return response()->json(['message' => 'Token is not bound to a workspace.'], Response::HTTP_UNAUTHORIZED);
        }

        if (! config('trypost.self_hosted') && ! $workspace->account?->hasActiveSubscription()) {
            return response()->json(['message' => 'Active subscription required.'], Response::HTTP_PAYMENT_REQUIRED);
        }

        $user->setRelation('currentWorkspace', $workspace);
        $user->current_workspace_id = $workspace->id;

        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        return $next($request);
    }
}
