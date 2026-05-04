<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Post;

use App\Http\Resources\Api\PostResource;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List posts for the current workspace, ordered by scheduled date (newest first).')]
class ListPostsTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $posts = $request->user()->currentWorkspace
            ->posts()
            ->with(['postPlatforms.socialAccount', 'labels'])
            ->latest('scheduled_at')
            ->limit(50)
            ->get();

        return Response::structured([
            'posts' => PostResource::collection($posts)->resolve(),
        ]);
    }
}
