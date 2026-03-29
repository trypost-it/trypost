<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Post;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List all posts for the current workspace. Returns posts with their platforms, status, and scheduled date.')]
class ListPostsTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $posts = $request->user()->currentWorkspace
            ->posts()
            ->with(['postPlatforms.socialAccount', 'labels'])
            ->latest('scheduled_at')
            ->paginate(50);

        return Response::structured($posts->toArray());
    }
}
