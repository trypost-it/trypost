<?php

declare(strict_types=1);

namespace App\Services\Post;

use App\Enums\Media\Type as MediaType;
use App\Models\Post;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Downloads public URLs and attaches them as media to a post — used by
 * the MCP `AttachMediaFromUrlTool` and the REST `attach-media-from-url`
 * endpoint.
 *
 * URL syntax + DNS resolvability are validated at the request layer
 * (`url:http,https`, `active_url`). Locking, intersection of accepted
 * media types, and the JSON-column merge live on the Post model so this
 * class can stay focused on the network → MIME-validate → handoff flow.
 */
class MediaAttacher
{
    /**
     * @param  array<int, string>  $urls
     * @return array{attached: array<int, array<string, mixed>>, failed: array<int, string>}
     */
    public function attachFromUrls(Post $post, array $urls): array
    {
        $attached = [];
        $failed = [];

        foreach ($urls as $url) {
            ($item = $this->attachOne($post, $url)) === null
                ? $failed[] = $url
                : $attached[] = $item;
        }

        if ($attached !== []) {
            $post->appendMedia($attached);
        }

        return ['attached' => $attached, 'failed' => $failed];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function attachOne(Post $post, string $url): ?array
    {
        $download = $this->download($url);

        if ($download === null) {
            return null;
        }

        try {
            $type = MediaType::fromMime($download['mime'] ?? '');

            if ($type === null || ! in_array($type, $post->allowedMediaTypes(), true)) {
                return null;
            }

            if ($download['bytes'] > $type->maxSizeInBytes()) {
                return null;
            }

            $name = basename(parse_url($url, PHP_URL_PATH) ?? '') ?: 'download.bin';
            $media = $post->workspace->addMediaFromPath($download['path'], $name, 'assets');

            return [
                'id' => $media->id,
                'path' => $media->path,
                'url' => $media->url,
                'type' => $media->type,
                'mime_type' => $media->mime_type,
                'original_filename' => $media->original_filename,
            ];
        } finally {
            @unlink($download['path']);
        }
    }

    /**
     * Stream the URL to a temp file, aborting once we exceed the largest
     * configured per-type cap (video). MIME is sniffed from the file's
     * magic bytes — far more reliable than trusting the upstream
     * `Content-Type` header (CDNs misconfigure, attackers spoof).
     *
     * @return array{path: string, mime: ?string, bytes: int}|null
     */
    private function download(string $url): ?array
    {
        $cap = MediaType::Video->maxSizeInBytes();
        $temp = tempnam(sys_get_temp_dir(), 'media_');

        try {
            $response = Http::timeout(20)
                ->sink($temp)
                ->withOptions([
                    'allow_redirects' => false,
                    'progress' => static function ($total, $downloaded) use ($cap): void {
                        if ($downloaded > $cap) {
                            throw new RuntimeException('exceeded max bytes');
                        }
                    },
                ])
                ->get($url);
        } catch (RuntimeException) {
            @unlink($temp);

            return null;
        }

        $bytes = filesize($temp) ?: 0;

        if (! $response->successful() || $bytes === 0) {
            @unlink($temp);

            return null;
        }

        return [
            'path' => $temp,
            'mime' => mime_content_type($temp) ?: null,
            'bytes' => $bytes,
        ];
    }
}
