<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Post\CreatePost;
use App\Actions\Post\DeletePost;
use App\Actions\Post\UpdatePost;
use App\Http\Resources\Api\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $posts = $request->workspace->posts()
            ->with(['postPlatforms.socialAccount', 'user', 'labels'])
            ->latest('scheduled_at')
            ->paginate(15);

        return PostResource::collection($posts);
    }

    public function show(Request $request, Post $post): PostResource
    {
        if ($post->workspace_id !== $request->workspace->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $post->load(['postPlatforms.socialAccount', 'user', 'labels']);

        return new PostResource($post);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*.social_account_id' => ['required', 'uuid'],
            'platforms.*.content_type' => ['required', 'string'],
            'platforms.*.content' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'status' => ['nullable', 'string', 'in:draft,scheduled,publishing'],
        ]);

        $post = CreatePost::execute(
            $request->workspace,
            $request->workspace->owner,
            $validated
        );

        $post->load(['postPlatforms.socialAccount']);

        return (new PostResource($post))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, Post $post): PostResource|JsonResponse
    {
        if ($post->workspace_id !== $request->workspace->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'platforms' => ['sometimes', 'array'],
            'platforms.*.id' => ['required', 'uuid'],
            'platforms.*.content' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:draft,scheduled,publishing'],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['uuid'],
        ]);

        $result = UpdatePost::execute($request->workspace, $post, $validated);

        if (data_get($result, 'action') === 'already_published') {
            return response()->json(
                ['message' => 'Cannot edit a published post.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return new PostResource(data_get($result, 'post'));
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        if ($post->workspace_id !== $request->workspace->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        DeletePost::execute($post);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
