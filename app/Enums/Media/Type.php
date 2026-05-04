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
     * @return array<int, string>
     */
    public function allowedMimeTypes(): array
    {
        return match ($this) {
            self::Image => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            self::Video => ['video/mp4', 'video/quicktime', 'video/webm'],
        };
    }

    /**
     * Filename extensions that match this type. Used by callers that
     * validate by name (chunked upload, URL fetch fallback) instead of
     * by MIME.
     *
     * @return array<int, string>
     */
    public function extensions(): array
    {
        return match ($this) {
            self::Image => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            self::Video => ['mp4', 'mov', 'webm'],
        };
    }

    public function maxSizeInMb(): int
    {
        return (int) config("trypost.media.max_size_mb.{$this->value}");
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
