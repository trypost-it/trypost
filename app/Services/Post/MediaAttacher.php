<?php

declare(strict_types=1);

namespace App\Services\Post;

use App\Enums\Media\Type as MediaType;
use App\Models\Media;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Downloads media from public URLs and attaches them to a post. Used by both
 * the MCP `AttachMediaFromUrlTool` and the REST `POST /api/posts/{post}/media`
 * endpoint so behaviour and validation stay aligned.
 */
class MediaAttacher
{
    private const ALLOWED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    private const ALLOWED_VIDEO_MIMES = ['video/mp4', 'video/quicktime', 'video/webm'];

    private const MAX_BYTES = 50 * 1024 * 1024; // 50 MB

    /**
     * @param  array<int, string>  $urls
     * @return array{attached: array<int, array<string, mixed>>, failed: array<int, string>}
     */
    public function attachFromUrls(Post $post, array $urls): array
    {
        $allowedTypes = $this->allowedMediaTypesFor($post);

        $existing = collect($post->media ?? []);
        $attached = [];
        $failed = [];

        foreach ($urls as $url) {
            $item = $this->downloadAndStore($post->workspace, $url, $allowedTypes);

            if ($item === null) {
                $failed[] = $url;

                continue;
            }

            $attached[] = $item;
        }

        if ($attached !== []) {
            $post->update([
                'media' => $existing->concat($attached)->all(),
            ]);
        }

        return ['attached' => $attached, 'failed' => $failed];
    }

    /**
     * Intersection of allowed media types across platforms enabled on the
     * post. If no platform is enabled, accept anything supported.
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

    /**
     * @param  array<MediaType>  $allowedTypes
     * @return array<string, mixed>|null
     */
    private function downloadAndStore(Workspace $workspace, string $url, array $allowedTypes): ?array
    {
        $response = Http::timeout(20)->get($url);

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();
        $bytes = strlen($body);

        if ($bytes === 0 || $bytes > self::MAX_BYTES) {
            return null;
        }

        $mime = $response->header('Content-Type');
        $mime = $mime ? trim(explode(';', $mime)[0]) : null;

        $type = $this->resolveType($mime);

        if ($type === null || ! in_array($type, $allowedTypes, true)) {
            return null;
        }

        $extension = $this->extensionFor($mime, $url);
        $filename = 'media/'.Str::uuid()->toString().'.'.$extension;
        $originalFilename = basename(parse_url($url, PHP_URL_PATH) ?? '') ?: 'download.'.$extension;

        Storage::put($filename, $body);

        $media = new Media([
            'collection' => 'post-media',
            'type' => $type,
            'path' => $filename,
            'original_filename' => $originalFilename,
            'mime_type' => $mime ?? '',
            'size' => $bytes,
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
