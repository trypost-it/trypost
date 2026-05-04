<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Post;

use App\Http\Resources\Api\PostResource;
use App\Models\Post;
use App\Services\Post\MediaAttacher;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Download images or videos from public URLs and attach them to a post. Each URL is fetched, stored, and registered as a Media record on the workspace. Allowed types are intersected with the platforms enabled on the post (e.g. nothing accepted if no platform supports the media type).')]
class AttachMediaFromUrlTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'post_id' => ['required', 'uuid'],
            'urls' => ['required', 'array', 'min:1', 'max:10'],
            'urls.*' => ['url:http,https'],
        ]);

        $post = Post::where('workspace_id', $request->user()->current_workspace_id)
            ->find(data_get($validated, 'post_id'));

        if (! $post) {
            return Response::error('Post not found.');
        }

        $result = app(MediaAttacher::class)->attachFromUrls(
            $post,
            data_get($validated, 'urls', []),
        );

        $post->refresh()->load(['postPlatforms.socialAccount', 'labels']);

        return Response::structured([
            'post' => (new PostResource($post))->resolve(),
            'attached_count' => count($result['attached']),
            'failed_urls' => $result['failed'],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->string()->required()->description('UUID of the post to attach media to.'),
            'urls' => $schema->array()
                ->items($schema->string())
                ->required()
                ->description('Public HTTP/HTTPS URLs of images or videos. Max 10 URLs per call, 50MB per file. Allowed types: image/jpeg, image/png, image/gif, image/webp, video/mp4, video/quicktime, video/webm.'),
        ];
    }
}
