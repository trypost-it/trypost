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
 *      callback that aborts once we exceed the largest configured
 *      per-type cap (video = 1 GB by default, see config/trypost.php).
 *   2. Resolve the MediaType from the response's Content-Type via
 *      `MediaType::fromMime()`. Reject if the type isn't accepted by
 *      the intersection of platforms enabled on the post.
 *   3. Enforce the per-type cap (`MediaType::Image->maxSizeInBytes()`
 *      vs `Video`) — a 100 MB jpeg is rejected even though we
 *      streamed up to the video cap.
 *   4. Hand off to `Workspace::addMediaFromPath()` so storage path,
 *      MIME re-detection, image normalization, and the Media row stay
 *      in one place (same path as the web upload flow).
 *   5. Append the resulting media item to the post's `media[]` JSON
 *      column under a row lock so concurrent attach calls don't clobber
 *      each other.
 */
class MediaAttacher
{
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
        // Use the largest configured per-type cap as the streaming-abort
        // threshold; the actual per-type limit is checked below once we
        // know the MIME.
        $streamCap = MediaType::Video->maxSizeInBytes();

        $temp = tempnam(sys_get_temp_dir(), 'media_');

        try {
            $response = Http::timeout(20)
                ->sink($temp)
                ->withOptions([
                    'allow_redirects' => false,
                    'progress' => static function ($total, $downloaded) use ($streamCap): void {
                        if ($downloaded > $streamCap) {
                            throw new RuntimeException('exceeded max bytes');
                        }
                    },
                ])
                ->get($url);

            $bytes = filesize($temp) ?: 0;

            if (! $response->successful() || $bytes === 0) {
                return null;
            }

            $mime = trim(explode(';', (string) $response->header('Content-Type'))[0]);
            $type = MediaType::fromMime($mime);

            if ($type === null || ! in_array($type, $allowedTypes, true)) {
                return null;
            }

            // Per-type size enforcement. Image is 10 MB even though we
            // streamed up to the video cap, so a 100 MB jpeg is rejected
            // here before we persist it.
            if ($bytes > $type->maxSizeInBytes()) {
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
}
