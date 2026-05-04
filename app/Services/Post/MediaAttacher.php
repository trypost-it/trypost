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
 * Flow per URL:
 *   1. Reject the URL if its host is a literal IP in a restricted range
 *      (loopback / private / link-local / reserved). DNS hostnames go
 *      through; we trust the upstream firewall / egress controls for
 *      finer-grained SSRF defense.
 *   2. Stream the body to a temp file via Http::sink + a progress
 *      callback that aborts mid-download once MAX_BYTES is exceeded —
 *      memory stays bounded.
 *   3. Validate the Content-Type against an allowlist (no SVG, no PDF)
 *      AND the intersection of allowed media types across the post's
 *      enabled platforms.
 *   4. Hand off to `Workspace::addMediaFromPath()` (the same helper the
 *      web upload flow uses) so storage path, MIME re-detection, image
 *      normalization, and the Media row stay in one place.
 *   5. Append the resulting media item to the post's `media[]` JSON
 *      column under a row lock so concurrent attach calls don't clobber
 *      each other.
 *
 * Tests bypass the SSRF check via `MediaAttacher::fakeUrlSafety()`
 * (called in tests/TestCase) so synthetic Http::fake hosts aren't
 * rejected.
 */
class MediaAttacher
{
    /**
     * Cap on URL-fetched payloads. Smaller than the web upload cap (which
     * can be 1 GB for direct uploads) because URL fetches have stricter
     * server-side concerns: bandwidth, timeout, and unbounded user input.
     * 50 MB covers a long photo or a short video; bigger files should be
     * uploaded directly.
     */
    private const MAX_BYTES = 50 * 1024 * 1024;

    private static bool $skipUrlSafety = false;

    public static function fakeUrlSafety(): void
    {
        self::$skipUrlSafety = true;
    }

    public static function resetUrlSafety(): void
    {
        self::$skipUrlSafety = false;
    }

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
        if (! $this->isUrlSafe($url)) {
            return null;
        }

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
     * Reject obvious SSRF targets: non-http(s) schemes, missing host,
     * and IP-literal hosts in private / loopback / link-local / reserved
     * ranges. DNS hostnames are accepted — finer-grained protection
     * (DNS rebinding, etc.) is left to network-level controls.
     */
    private function isUrlSafe(string $url): bool
    {
        if (self::$skipUrlSafety) {
            return true;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array(data_get($parts, 'scheme'), ['http', 'https'], true)) {
            return false;
        }

        $host = data_get($parts, 'host');

        if (! is_string($host) || $host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) !== false;
        }

        return true;
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
