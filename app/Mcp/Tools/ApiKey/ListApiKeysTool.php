<?php

declare(strict_types=1);

namespace App\Mcp\Tools\ApiKey;

use App\Models\AccessToken;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List all API keys for the current workspace.')]
class ListApiKeysTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $tokens = AccessToken::where('user_id', $request->user()->id)
            ->where('workspace_id', $request->user()->currentWorkspace->id)
            ->where('revoked', false)
            ->latest()
            ->get(['id', 'name', 'expires_at', 'last_used_at', 'created_at']);

        return Response::structured($tokens->toArray());
    }
}
