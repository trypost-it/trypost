<?php

declare(strict_types=1);

namespace App\Services\Post;

use App\Enums\Media\Type as MediaType;
use App\Models\Media;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Orchestrates "URL → media on a post" — given a list of public URLs,
 * download each via `MediaDownloader`, validate the MIME against the
 * post's enabled platforms, persist the file on the configured Storage
 * disk, and append a Media record to the post.
 *
 * Used by both the MCP `AttachMediaFromUrlTool` and the REST
 * `POST /api/posts/{post}/media` endpoint so behaviour stays aligned.
 */
class MediaAttacher
{
    private const ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    private const ALLOWED_VIDEO_MIMES = ['video/mp4', 'video/quicktime', 'video/webm'];

    private const MAX_BYTES = 50 * 1024 * 1024; // 50 MB

    public function __construct(
        private readonly MediaDownloader $downloader,
    ) {}

    /**
     * @param  array<int, string>  $urls
     * @return array{attached: array<int, array<string, mixed>>, failed: array<int, string>}
     */
    public function attachFromUrls(Post $post, array $urls): array
    {
        $allowedTypes = $this->allowedMediaTypesFor($post);

        $attached = [];
        $failed = [];

        foreach ($urls as $url) {
            $item = $this->processOne($post->workspace, $url, $allowedTypes);

            if ($item === null) {
                $failed[] = $url;

                continue;
            }

            $attached[] = $item;
        }

        if ($attached !== []) {
            $this->mergeIntoPostMedia($post, $attached);
        }

        return ['attached' => $attached, 'failed' => $failed];
    }

    /**
     * @param  array<MediaType>  $allowedTypes
     * @return array<string, mixed>|null
     */
    private function processOne(Workspace $workspace, string $url, array $allowedTypes): ?array
    {
        $download = $this->downloader->download($url, self::MAX_BYTES);

        if ($download === null) {
            return null;
        }

        try {
            $type = $this->resolveType(data_get($download, 'mime'));

            if ($type === null || ! in_array($type, $allowedTypes, true)) {
                return null;
            }

            return $this->storeMedia($workspace, $download, $type, $url);
        } finally {
            @unlink(data_get($download, 'path'));
        }
    }

    /**
     * @param  array{path: string, mime: ?string, bytes: int}  $download
     * @return array<string, mixed>
     */
    private function storeMedia(Workspace $workspace, array $download, MediaType $type, string $url): array
    {
        $mime = data_get($download, 'mime');
        $extension = $this->extensionFor($mime, $url);
        $filename = 'media/'.Str::uuid()->toString().'.'.$extension;
        $originalFilename = basename(parse_url($url, PHP_URL_PATH) ?? '') ?: 'download.'.$extension;

        Storage::putFileAs('', new File(data_get($download, 'path')), $filename);

        $media = new Media([
            'collection' => 'post-media',
            'type' => $type,
            'path' => $filename,
            'original_filename' => $originalFilename,
            'mime_type' => $mime ?? '',
            'size' => data_get($download, 'bytes'),
            'order' => 0,
        ]);
        $media->mediable_type = Workspace::class;
        $media->mediable_id = $workspace->id;
        $media->save();

        return [
            'id' => $media->id,
            'path' => $media->path,
            'url' => $media->url,
            'type' => $type->value,
            'mime_type' => $media->mime_type,
            'original_filename' => $media->original_filename,
        ];
    }

    /**
     * Lock-then-merge so concurrent attach calls don't overwrite each
     * other's appended items in the JSON `media` column.
     *
     * @param  array<int, array<string, mixed>>  $attached
     */
    private function mergeIntoPostMedia(Post $post, array $attached): void
    {
        DB::transaction(function () use ($post, $attached): void {
            $fresh = Post::whereKey($post->id)->lockForUpdate()->first();
            $fresh->update([
                'media' => collect($fresh->media ?? [])->concat($attached)->all(),
            ]);
            $post->setRawAttributes($fresh->getAttributes(), true);
        });
    }

    /**
     * Intersection of allowed media types across platforms enabled on
     * the post. With no enabled platform, accept anything we support.
     *
     * @return array<MediaType>
     */
    private function allowedMediaTypesFor(Post $post): array
    {
        $enabledPlatforms = $post->postPlatforms()
            ->where('enabled', true)
            ->with('socialAccount')
            ->get()
            ->pluck('socialAccount.platform')
            ->filter();

        if ($enabledPlatforms->isEmpty()) {
            return [MediaType::Image, MediaType::Video];
        }

        $sets = $enabledPlatforms
            ->map(fn ($platform) => array_map(fn ($type) => $type->value, $platform->allowedMediaTypes()))
            ->all();

        $intersection = array_values(array_intersect(...$sets));

        return array_map(fn ($value) => MediaType::from($value), $intersection);
    }

    private function resolveType(?string $mime): ?MediaType
    {
        if ($mime === null) {
            return null;
        }

        if (in_array($mime, self::ALLOWED_IMAGE_MIMES, true)) {
            return MediaType::Image;
        }

        if (in_array($mime, self::ALLOWED_VIDEO_MIMES, true)) {
            return MediaType::Video;
        }

        return null;
    }

    private function extensionFor(?string $mime, string $url): string
    {
        $byMime = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'video/webm' => 'webm',
            default => null,
        };

        if ($byMime) {
            return $byMime;
        }

        $byUrl = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        return in_array($byUrl, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'webm'], true)
            ? $byUrl
            : 'bin';
    }
}
