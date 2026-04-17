<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Workspace;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get the current workspace details including name.')]
class GetWorkspaceTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        return Response::structured($request->user()->currentWorkspace->toArray());
    }
}
