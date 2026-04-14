<?php

declare(strict_types=1);

namespace App\Enums\Plan;

enum Slug: string
{
    case Starter = 'starter';
    case Plus = 'plus';
    case Pro = 'pro';
    case Max = 'max';

    public function label(): string
    {
        return match ($this) {
            self::Starter => 'Starter',
            self::Plus => 'Plus',
            self::Pro => 'Pro',
            self::Max => 'Max',
        };
    }
}
