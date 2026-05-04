<?php

declare(strict_types=1);

namespace App\Services\Post;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Downloads a single media file from a public URL into a local temporary
 * file, streaming the body to disk so we don't buffer it in PHP memory.
 * Aborts mid-stream once `maxBytes` is exceeded.
 *
 * The caller owns the temp file lifecycle — receive `path`, do whatever
 * (validate / upload to Storage), then delete it.
 */
class MediaDownloader
{
    public function __construct(
        private readonly UrlSafetyGuard $guard,
    ) {}

    /**
     * @return array{path: string, mime: ?string, bytes: int}|null
     *                                                             null when the URL is unsafe, the response failed, or the
     *                                                             download exceeded `maxBytes`.
     */
    public function download(string $url, int $maxBytes): ?array
    {
        if (! $this->guard->isSafe($url)) {
            return null;
        }

        $temp = tempnam(sys_get_temp_dir(), 'media_');

        try {
            $response = Http::timeout(20)
                ->sink($temp)
                ->withOptions([
                    'allow_redirects' => false,
                    'progress' => static function ($total, $downloaded) use ($maxBytes): void {
                        if ($downloaded > $maxBytes) {
                            throw new RuntimeException('exceeded max bytes');
                        }
                    },
                ])
                ->get($url);
        } catch (RuntimeException) {
            @unlink($temp);

            return null;
        }

        if (! $response->successful()) {
            @unlink($temp);

            return null;
        }

        $bytes = filesize($temp) ?: 0;

        if ($bytes === 0 || $bytes > $maxBytes) {
            @unlink($temp);

            return null;
        }

        $mime = $response->header('Content-Type');
        $mime = $mime ? trim(explode(';', $mime)[0]) : null;

        return [
            'path' => $temp,
            'mime' => $mime,
            'bytes' => $bytes,
        ];
    }
}
