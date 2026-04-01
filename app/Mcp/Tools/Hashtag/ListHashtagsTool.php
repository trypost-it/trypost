<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Hashtag;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List all hashtag groups for the current workspace.')]
class ListHashtagsTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $hashtags = $request->user()->currentWorkspace->hashtags()->latest()->get();

        return Response::structured($hashtags->toArray());
    }
}
