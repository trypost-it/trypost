<?php

declare(strict_types=1);

namespace App\Enums\Workspace;

/**
 * Visual style the AI image generator will lean into when rendering slides
 * and cover images for a workspace's posts. Each case maps to a prompt
 * template strategy in the image generation pipeline.
 */
enum ImageStyle: string
{
    case Cinematic = 'cinematic';
    case Illustration = 'illustration';
    case Isometric3D = 'isometric_3d';
    case Cartoon = 'cartoon';
    case Typographic = 'typographic';
    case Infographic = 'infographic';
    case Minimalist = 'minimalist';
    case Mockup = 'mockup';

    public const DEFAULT = self::Cinematic;

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
