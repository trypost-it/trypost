<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Post\CreatePost;
use App\Enums\Media\Type as MediaType;
use App\Enums\PostPlatform\ContentType;
use App\Http\Requests\App\Ai\StartPostCreationRequest;
use App\Jobs\Ai\StreamPostCreation;
use App\Models\Media;
use App\Models\SocialAccount;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PostAiCreateController extends Controller
{
    public function start(StartPostCreationRequest $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $socialAccountId = $request->input('social_account_id');

        if ($socialAccountId) {
            $owned = SocialAccount::where('id', $socialAccountId)
                ->where('workspace_id', $workspace->id)
                ->exists();

            if (! $owned) {
                abort(Response::HTTP_FORBIDDEN);
            }
        }

        $creationId = (string) Str::uuid();

        StreamPostCreation::dispatch(
            userId: $request->user()->id,
            creationId: $creationId,
            workspaceId: $workspace->id,
            format: $request->string('format')->toString(),
            socialAccountId: $socialAccountId,
            imageCount: (int) $request->input('image_count', 0),
            prompt: $request->string('prompt')->toString(),
        );

        return response()->json([
            'creation_id' => $creationId,
            'channel' => "users.{$request->user()->id}.ai-creation.{$creationId}",
        ], Response::HTTP_ACCEPTED);
    }

    public function finalize(Request $request, string $creationId): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $state = Cache::get("ai-creation:{$creationId}");

        if (! $state || data_get($state, 'user_id') !== $request->user()->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $format = data_get($state, 'format');
        $socialAccountId = data_get($state, 'social_account_id');
        $contentType = $format ? ContentType::tryFrom($format) : null;

        // The wizard's preview is editable. Frontend sends whichever fields it
        // showed: caption for normal formats, image_title + image_body for
        // stories. We override the cached state with whatever was edited.
        if ($request->filled('content')) {
            $state['content'] = (string) $request->input('content');
        }
        if ($request->filled('image_title')) {
            $state['image_title'] = (string) $request->input('image_title');
        }
        if ($request->filled('image_body')) {
            $state['image_body'] = (string) $request->input('image_body');
        }

        $media = $this->buildMediaArray($workspace, $state);

        $caption = ($contentType && ! $contentType->supportsCaption())
            ? ''
            : (string) data_get($state, 'content', '');

        $post = CreatePost::execute($workspace, $request->user(), [
            'content' => $caption,
            'media' => $media,
        ]);

        // Sync the post_platform with the wizard choice: set content_type to
        // match the format (so the editor surfaces "Story" instead of "Post"
        // for instance) and aspect_ratio so the preview matches the rendered
        // image exactly. Carousel collapses to feed since Instagram doesn't
        // expose carousel as a separate content_type at the API level.
        if ($contentType && $socialAccountId) {
            $aspectRatio = $this->aspectRatioFor($contentType);
            $platformContentType = $contentType === ContentType::InstagramCarousel
                ? ContentType::InstagramFeed
                : $contentType;

            $post->postPlatforms()
                ->where('social_account_id', $socialAccountId)
                ->each(function ($platform) use ($aspectRatio, $platformContentType): void {
                    $meta = $platform->meta ?? [];
                    if ($aspectRatio !== null) {
                        $meta['aspect_ratio'] = $aspectRatio;
                    }
                    $platform->meta = $meta;
                    $platform->content_type = $platformContentType->value;
                    $platform->enabled = true;
                    $platform->save();
                });
        }

        Cache::forget("ai-creation:{$creationId}");

        return response()->json([
            'post_id' => $post->id,
            'redirect_url' => route('app.posts.edit', $post),
        ]);
    }

    /**
     * Map the AI image dimensions to the aspect_ratio string the editor's
     * preview understands. Returns null when the size doesn't match a known
     * preview ratio (Instagram preview supports 1:1, 4:5, 16:9, original).
     */
    private function aspectRatioFor(ContentType $type): ?string
    {
        $dims = $type->aiImageDimensions();
        $ratio = $dims['width'] / $dims['height'];

        return match (true) {
            abs($ratio - 1.0) < 0.01 => '1:1',
            abs($ratio - 4 / 5) < 0.01 => '4:5',
            abs($ratio - 16 / 9) < 0.01 => '16:9',
            default => null,
        };
    }

    /**
     * Build the media array for post creation from the AI creation state.
     *
     * @param  array<string, mixed>  $state
     * @return array<int, array<string, mixed>>
     */
    private function buildMediaArray(Workspace $workspace, array $state): array
    {
        $media = [];

        if (data_get($state, 'format') === 'instagram_carousel') {
            foreach (data_get($state, 'slides', []) as $slide) {
                $path = data_get($slide, 'image_path');
                if ($path) {
                    $media[] = $this->createMediaItem($workspace, $path, [
                        'slide_title' => data_get($slide, 'title'),
                        'slide_body' => data_get($slide, 'body'),
                        'slide_keywords' => data_get($slide, 'image_keywords', []),
                        'is_closing' => (bool) data_get($slide, 'is_closing', false),
                    ]);
                }
            }
        } else {
            $path = data_get($state, 'image_path');
            if ($path) {
                $media[] = $this->createMediaItem($workspace, $path, [
                    'slide_title' => data_get($state, 'image_title'),
                    'slide_body' => data_get($state, 'image_body'),
                    'slide_keywords' => data_get($state, 'image_keywords', []),
                ]);
            }
        }

        return $media;
    }

    /**
     * Create a Media record for an AI-generated image and return it as an array.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function createMediaItem(Workspace $workspace, string $path, array $meta = []): array
    {
        $media = new Media([
            'collection' => 'ai-generated',
            'type' => MediaType::Image,
            'path' => $path,
            'original_filename' => basename($path),
            'mime_type' => 'image/webp',
            'size' => Storage::size($path),
            'order' => 0,
        ]);

        $media->mediable_type = Workspace::class;
        $media->mediable_id = $workspace->id;
        $media->save();

        return [
            'id' => $media->id,
            'path' => $media->path,
            'url' => $media->url,
            'type' => 'image',
            'mime_type' => 'image/webp',
            'meta' => $meta,
        ];
    }
}
