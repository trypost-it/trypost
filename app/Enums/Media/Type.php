<?php

declare(strict_types=1);

namespace App\Enums\Media;

enum Type: string
{
    case Image = 'image';
    case Video = 'video';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Imagem',
            self::Video => 'Vídeo',
        };
    }

    /**
     * Allow-list of MIME types we accept on upload / URL fetch.
     *
     * Video accepts MP4 plus QuickTime/MOV. Modern .mov files (iPhone
     * recordings, screen captures) are ISO BMFF containers — the same
     * format MP4 uses — so social platforms decode them like MP4 even
     * if PHP reports `video/quicktime`. Accepting MOV avoids forcing
     * iPhone users to transcode before uploading.
     *
     * WebM is rejected: X / IG / TikTok / FB / Pinterest / Bluesky /
     * Threads all reject the Matroska + VP8/VP9 stack. Without
     * server-side transcoding, accepting WebM would just produce
     * platform-specific publish failures down the line.
     *
     * @return array<int, string>
     */
    public function allowedMimeTypes(): array
    {
        return match ($this) {
            self::Image => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            self::Video => ['video/mp4', 'video/quicktime'],
        };
    }

    /**
     * Filename extensions that match this type. Mirrors allowedMimeTypes
     * for callers that validate by name instead of MIME.
     *
     * @return array<int, string>
     */
    public function extensions(): array
    {
        return match ($this) {
            self::Image => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            self::Video => ['mp4', 'mov'],
        };
    }

    public function maxSizeInMb(): int
    {
        return (int) config("postpro.media.max_size_mb.{$this->value}");
    }

    public function maxSizeInBytes(): int
    {
        return $this->maxSizeInMb() * 1024 * 1024;
    }

    public function maxSizeInKb(): int
    {
        return $this->maxSizeInMb() * 1024;
    }

    /**
     * Resolve a Type from a MIME string. Returns null when the MIME is
     * not in any type's allow-list.
     */
    public static function fromMime(string $mime): ?self
    {
        foreach (self::cases() as $type) {
            if (in_array($mime, $type->allowedMimeTypes(), true)) {
                return $type;
            }
        }

        return null;
    }
}

