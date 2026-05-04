<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Post;

use App\Actions\Post\CreatePost;
use App\Enums\PostPlatform\ContentType;
use App\Http\Resources\Api\PostResource;
use App\Rules\ContentTypeMatchesPlatform;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a draft post in the current workspace. Accepts content, scheduled_at, label_ids, and a list of platforms (social accounts to publish on, with their content_type). Use list-content-types-tool to discover valid content_types per platform.')]
class CreatePostTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $workspace = $request->user()->currentWorkspace;

        $validated = $request->validate([
            'content' => ['nullable', 'string', 'max:63206'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['uuid', Rule::exists('workspace_labels', 'id')->where('workspace_id', $workspace->id)],
            'platforms' => ['sometimes', 'array'],
            'platforms.*.social_account_id' => [
                'required',
                'uuid',
                Rule::exists('social_accounts', 'id')
                    ->where('workspace_id', $workspace->id)
                    ->where('is_active', true),
            ],
            'platforms.*.content_type' => ['required', 'string', Rule::in(array_column(ContentType::cases(), 'value')), new ContentTypeMatchesPlatform],
        ]);

        $post = CreatePost::execute($workspace, $request->user(), $validated);

        $post->load(['postPlatforms.socialAccount', 'labels']);

        return Response::structured((new PostResource($post))->resolve());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema->string()->description('The post caption/text body. Optional — can be edited later.'),
            'scheduled_at' => $schema->string()->description('ISO 8601 datetime in the future (e.g. 2026-05-10T15:30:00Z). Defaults to today at 09:00 UTC.'),
            'label_ids' => $schema->array()
                ->items($schema->string())
                ->description('Workspace label IDs to attach to the post.'),
            'platforms' => $schema->array()
                ->items($schema->object(fn ($p) => [
                    'social_account_id' => $p->string()->required()->description('UUID of the connected social account.'),
                    'content_type' => $p->string()->required()->description('Format for this platform (e.g. linkedin_post, x_post, instagram_feed).'),
                ]))
                ->description('Platforms to publish on. Accounts not listed remain available but disabled.'),
        ];
    }
}
