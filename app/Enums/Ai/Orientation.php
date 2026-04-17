<?php

declare(strict_types=1);

namespace App\Enums\Ai;

enum Orientation: string
{
    case Square = 'square';
    case Portrait = 'portrait';
    case Vertical = 'vertical';
    case Horizontal = 'horizontal';

    public function aspectRatio(): string
    {
        return match ($this) {
            self::Square => '1:1',
            self::Portrait => '4:5',
            self::Vertical => '9:16',
            self::Horizontal => '16:9',
        };
    }
}
