<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Post;

use App\Actions\Post\CreatePost;
use App\Http\Resources\Api\PostResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new draft post in the current workspace. The post is automatically attached to every active social account in the workspace (one PostPlatform per account).')]
class CreatePostTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'content' => ['nullable', 'string'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $post = CreatePost::execute(
            $request->user()->currentWorkspace,
            $request->user(),
            $validated,
        );

        $post->load(['postPlatforms.socialAccount', 'labels']);

        return Response::structured((new PostResource($post))->resolve());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema->string()->description('The post caption/text content. Optional — can be edited later.'),
            'date' => $schema->string()->description('Scheduled date as Y-m-d (e.g. 2026-05-10). Defaults to today. Time is fixed at 09:00 UTC; edit the post later for a specific time.'),
        ];
    }
}
