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
    public function handle(Request $request): Response|ResponseFactory
    {
        $post = Post::where('workspace_id', $request->user()->current_workspace_id)
            ->find(data_get($request->validate(['post_id' => ['required', 'string']]), 'post_id'));

        if (! $post) {
            return Response::error('Post not found.');
        }

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
