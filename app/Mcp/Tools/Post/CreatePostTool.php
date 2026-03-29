<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Post;

use App\Actions\Post\CreatePost;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new draft post in the current workspace. A post platform entry is created for each connected social account.')]
class CreatePostTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $workspace = $request->user()->currentWorkspace;
        $post = CreatePost::execute($workspace, $request->user(), $request->validated());
        $post->load(['postPlatforms.socialAccount']);

        return Response::structured($post->toArray());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()->description('The scheduled date (Y-m-d). Defaults to today.'),
        ];
    }
}
