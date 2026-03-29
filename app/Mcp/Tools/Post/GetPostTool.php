<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Post;

use App\Models\Post;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get a specific post by ID with all its platform content and labels.')]
class GetPostTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $post = Post::where('workspace_id', $request->user()->current_workspace_id)
            ->with(['postPlatforms.socialAccount', 'postPlatforms.media', 'labels'])
            ->findOrFail(data_get($request->validated(), 'post_id'));

        return Response::structured($post->toArray());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->string()->required()->description('The post ID to retrieve.'),
        ];
    }
}
