<?php

declare(strict_types=1);

namespace App\Enums\Ai;

enum Orientation: string
{
    case Vertical = 'vertical';
    case Horizontal = 'horizontal';

    public function aspectRatio(): string
    {
        return match ($this) {
            self::Vertical => '9:16',
            self::Horizontal => '16:9',
        };
    }
}
