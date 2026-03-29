<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Post;

use App\Actions\Post\DeletePost;
use App\Models\Post;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a post by ID.')]
class DeletePostTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $post = Post::where('workspace_id', $request->user()->current_workspace_id)
            ->findOrFail(data_get($request->validated(), 'post_id'));

        DeletePost::execute($post);

        return Response::structured(['deleted' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->string()->required()->description('The post ID to delete.'),
        ];
    }
}
