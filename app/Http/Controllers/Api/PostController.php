<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Post\CreatePost;
use App\Actions\Post\DeletePost;
use App\Actions\Post\UpdatePost;
use App\Enums\Media\Type as MediaType;
use App\Enums\Post\Action as PostAction;
use App\Http\Requests\Api\Post\AttachMediaFromUrlRequest;
use App\Http\Requests\Api\Post\StoreMediaRequest;
use App\Http\Requests\Api\Post\StorePostRequest;
use App\Http\Requests\Api\Post\UpdatePostRequest;
use App\Http\Resources\Api\PostMediaAttachResource;
use App\Http\Resources\Api\PostMetricsResource;
use App\Http\Resources\Api\PostPreviewResource;
use App\Http\Resources\Api\PostResource;
use App\Models\Post;
use App\Services\Post\MediaAttacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $posts = $request->user()->currentWorkspace->posts()
            ->with(['postPlatforms.socialAccount', 'user', 'labels'])
            ->latest('scheduled_at')
            ->paginate(15);

        return PostResource::collection($posts);
    }

    public function show(Request $request, Post $post): PostResource
    {
        $this->authorize('view', $post);

        $post->load(['postPlatforms.socialAccount', 'user', 'labels']);

        return new PostResource($post);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = CreatePost::execute(
            $request->user()->currentWorkspace,
            $request->user()->currentWorkspace->owner,
            $request->validated()
        );

        $post->load(['postPlatforms.socialAccount']);

        return (new PostResource($post))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdatePostRequest $request, Post $post): PostResource|JsonResponse
    {
        $this->authorize('update', $post);

        $result = UpdatePost::execute($request->user()->currentWorkspace, $post, $request->validated());

        if (in_array(data_get($result, 'action'), [PostAction::AlreadyPublished, PostAction::Finalized], true)) {
            return response()->json(
                ['message' => 'Cannot edit a published post.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return new PostResource(data_get($result, 'post'));
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        DeletePost::execute($post);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function storeMedia(StoreMediaRequest $request, Post $post): PostResource
    {
        $this->authorize('update', $post);

        $file = $request->file('media');
        $type = MediaType::fromMime((string) $file->getMimeType());

        if ($type === null || ! in_array($type, $post->allowedMediaTypes(), true)) {
            throw ValidationException::withMessages([
                'media' => 'This file type is not supported by the platforms enabled on the post.',
            ]);
        }

        if ($file->getSize() > $type->maxSizeInBytes()) {
            throw ValidationException::withMessages([
                'media' => 'File size exceeds the maximum allowed for this media type.',
            ]);
        }

        $media = $post->workspace->addMedia($file, 'assets');

        $post->appendMedia([[
            'id' => $media->id,
            'path' => $media->path,
            'url' => $media->url,
            'type' => $media->type,
            'mime_type' => $media->mime_type,
            'original_filename' => $media->original_filename,
        ]]);

        $post->refresh()->load(['postPlatforms.socialAccount', 'labels']);

        return new PostResource($post);
    }

    public function attachMediaFromUrl(AttachMediaFromUrlRequest $request, Post $post): PostMediaAttachResource
    {
        $this->authorize('update', $post);

        $result = app(MediaAttacher::class)->attachFromUrls($post, $request->validated('urls'));

        $post->refresh()->load(['postPlatforms.socialAccount', 'labels']);

        return new PostMediaAttachResource($post, $result);
    }

    public function metrics(Request $request, Post $post): PostMetricsResource
    {
        $this->authorize('view', $post);

        $post->load(['postPlatforms.socialAccount']);

        return new PostMetricsResource($post);
    }

    public function preview(Request $request, Post $post): PostPreviewResource
    {
        $this->authorize('view', $post);

        $post->load(['postPlatforms.socialAccount']);

        return new PostPreviewResource($post);
    }
}
