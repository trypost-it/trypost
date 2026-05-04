<?php

declare(strict_types=1);

namespace App\Services\Post;

use App\Enums\Media\Type as MediaType;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Downloads public URLs and attaches them as media to a post. Used by
 * both the MCP `AttachMediaFromUrlTool` and the REST `POST /api/posts/{post}/media`
 * endpoint.
 *
 * URL syntax (`url:http,https`) and DNS resolvability (`active_url`) are
 * enforced at the request validation layer. SSRF defense beyond that is
 * the responsibility of network-level egress controls in production.
 *
 * Flow per URL:
 *   1. Stream the body to a temp file via Http::sink + a progress
 *      callback that aborts mid-download once MAX_BYTES is exceeded.
 *   2. Validate the Content-Type against the MediaType enum's allow-list
 *      AND the intersection of allowed media types across the post's
 *      enabled platforms.
 *   3. Hand off to `Workspace::addMediaFromPath()` so storage path,
 *      MIME re-detection, image normalization, and the Media row stay
 *      in one place (same path as the web upload flow).
 *   4. Append the resulting media item to the post's `media[]` JSON
 *      column under a row lock so concurrent attach calls don't clobber
 *      each other.
 */
class MediaAttacher
{
    /**
     * Cap on URL-fetched payloads. Smaller than the web upload cap (1 GB)
     * because URL fetches have different operational constraints:
     * bandwidth, timeout, and unbounded user input. 50 MB covers a long
     * photo or a short video; bigger files should be uploaded directly.
     */
    private const MAX_BYTES = 50 * 1024 * 1024;

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
        $temp = tempnam(sys_get_temp_dir(), 'media_');

        try {
            $response = Http::timeout(20)
                ->sink($temp)
                ->withOptions([
                    'allow_redirects' => false,
                    'progress' => static function ($total, $downloaded): void {
                        if ($downloaded > self::MAX_BYTES) {
                            throw new RuntimeException('exceeded max bytes');
                        }
                    },
                ])
                ->get($url);

            if (! $response->successful() || filesize($temp) === 0) {
                return null;
            }

            $mime = trim(explode(';', (string) $response->header('Content-Type'))[0]);
            $type = $this->resolveType($mime);

            if ($type === null || ! in_array($type, $allowedTypes, true)) {
                return null;
            }

            $originalFilename = basename(parse_url($url, PHP_URL_PATH) ?? '') ?: 'download.bin';
            $media = $workspace->addMediaFromPath($temp, $originalFilename, 'assets');

            return [
                'id' => $media->id,
                'path' => $media->path,
                'url' => $media->url,
                'type' => $media->type,
                'mime_type' => $media->mime_type,
                'original_filename' => $media->original_filename,
            ];
        } catch (RuntimeException) {
            return null;
        } finally {
            @unlink($temp);
        }
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

    /**
     * Resolve the MediaType for a given MIME by walking the enum's own
     * allow-list. Document is excluded from URL fetches (no PDFs via URL
     * — those go through direct upload only).
     */
    private function resolveType(?string $mime): ?MediaType
    {
        if ($mime === null || $mime === '') {
            return null;
        }

        foreach ([MediaType::Image, MediaType::Video] as $type) {
            if (in_array($mime, $type->allowedMimeTypes(), true)) {
                return $type;
            }
        }

        return null;
    }
}
