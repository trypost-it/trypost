<?php

namespace App\Enums\Media;

enum Type: string
{
    case Image = 'image';
    case Video = 'video';
    case Document = 'document';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Imagem',
            self::Video => 'Vídeo',
            self::Document => 'Documento',
        };
    }

    public function allowedMimeTypes(): array
    {
        return match ($this) {
            self::Image => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            self::Video => ['video/mp4', 'video/quicktime', 'video/webm'],
            self::Document => ['application/pdf'],
        };
    }

    public function maxSizeInMb(): int
    {
        return match ($this) {
            self::Image => 10,
            self::Video => 2048,
            self::Document => 100,
        };
    }
}
