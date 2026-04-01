<?php

declare(strict_types=1);

namespace App\Mcp\Tools\ApiKey;

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
        $tokens = $request->user()->currentWorkspace->apiTokens()->latest()->get();

        return Response::structured($tokens->toArray());
    }
}
