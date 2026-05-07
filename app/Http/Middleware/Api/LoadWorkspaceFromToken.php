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

        // Personal API tokens (created from settings) bind to a specific
        // workspace at creation. OAuth tokens (e.g. ChatGPT MCP) don't —
        // they follow the user's current workspace.
        $workspace = $token->workspace_id
            ? Workspace::find($token->workspace_id)
            : $user->currentWorkspace;

        if (! $workspace) {
            return response()->json(['message' => 'No workspace selected.'], Response::HTTP_UNAUTHORIZED);
        }

        if (! config('postpro.self_hosted') && ! $workspace->account?->hasActiveSubscription()) {
            return response()->json(['message' => 'Active subscription required.'], Response::HTTP_PAYMENT_REQUIRED);
        }

        $user->setRelation('currentWorkspace', $workspace);
        $user->current_workspace_id = $workspace->id;

        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        return $next($request);
    }
}

