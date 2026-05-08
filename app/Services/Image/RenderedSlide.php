<?php

declare(strict_types=1);

namespace App\Services\Image;

class RenderedSlide
{
    /**
     * @param  array<string, mixed>  $sourceMeta
     */
    public function __construct(
        public readonly string $path,
        public readonly array $sourceMeta,
    ) {}
}
