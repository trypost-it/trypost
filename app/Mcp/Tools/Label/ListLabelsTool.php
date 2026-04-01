<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Label;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List all labels for the current workspace.')]
class ListLabelsTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $labels = $request->user()->currentWorkspace->labels()->latest()->get();

        return Response::structured($labels->toArray());
    }
}
