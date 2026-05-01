<?php

declare(strict_types=1);

namespace App\Enums\Ai;

enum Orientation: string
{
    case Square = 'square';
    case Portrait = 'portrait';
    case Vertical = 'vertical';
    case Horizontal = 'horizontal';

    /**
     * Aspect ratio used in prompts to describe the desired framing to the model.
     */
    public function aspectRatio(): string
    {
        return match ($this) {
            self::Square => '1:1',
            self::Portrait => '4:5',
            self::Vertical => '9:16',
            self::Horizontal => '16:9',
        };
    }

    /**
     * Size string supported by the AI image SDK (1:1 / 2:3 / 3:2).
     *
     * OpenAI's image API only accepts these three ratios, so we map every
     * orientation to the closest supported one. Gemini accepts the same set.
     */
    public function imageApiSize(): string
    {
        return match ($this) {
            self::Square => '1:1',
            self::Portrait, self::Vertical => '2:3',
            self::Horizontal => '3:2',
        };
    }
}
