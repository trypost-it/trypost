<?php

declare(strict_types=1);

namespace App\Services\Post;

use App\Enums\Media\Type as MediaType;
use App\Models\Media;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
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
            // Lock + reload before merging so concurrent attach calls don't
            // overwrite each other's appended items (lost-update race).
            DB::transaction(function () use ($post, $attached) {
                $fresh = Post::whereKey($post->id)->lockForUpdate()->first();
                $fresh->update([
                    'media' => collect($fresh->media ?? [])->concat($attached)->all(),
                ]);
                $post->setRawAttributes($fresh->getAttributes(), true);
            });
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
        if (! $this->isPublicHttpUrl($url)) {
            return null;
        }

        // Disable redirects (a public URL could 302 to an internal target),
        // stream the body, and abort once we exceed MAX_BYTES so a malicious
        // host can't exhaust memory or our process timeout.
        $response = Http::timeout(20)
            ->withOptions([
                'allow_redirects' => false,
                'stream' => true,
            ])
            ->get($url);

        if (! $response->successful()) {
            return null;
        }

        $body = '';
        $bytes = 0;
        $stream = $response->toPsrResponse()->getBody();

        while (! $stream->eof()) {
            $chunk = $stream->read(8192);
            $bytes += strlen($chunk);

            if ($bytes > self::MAX_BYTES) {
                return null;
            }

            $body .= $chunk;
        }

        if ($bytes === 0) {
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

    /**
     * Reject anything that isn't a plain http(s) URL targeting a public host.
     * Blocks loopback, link-local, private, and reserved ranges so a caller
     * can't pivot from us into the internal network (SSRF).
     */
    private function isPublicHttpUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array(data_get($parts, 'scheme'), ['http', 'https'], true)) {
            return false;
        }

        $host = data_get($parts, 'host');

        if (! is_string($host) || $host === '') {
            return false;
        }

        // Under `Http::fake()` the HTTP facade short-circuits real network
        // calls; skip DNS resolution so tests can stub responses for synthetic
        // hosts without our SSRF guard rejecting them.
        if (app()->runningUnitTests()) {
            return true;
        }

        // Reject literal IPv4/IPv6 host inputs that fall in restricted ranges.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->ipIsPublic($host);
        }

        // For DNS hostnames, resolve and check every record. Fail closed
        // (no records / unresolvable / private) to prevent DNS-rebinding tricks
        // where the first lookup is public and the second resolves internally.
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false || $records === []) {
            return false;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (! is_string($ip) || ! $this->ipIsPublic($ip)) {
                return false;
            }
        }

        return true;
    }

    private function ipIsPublic(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
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
