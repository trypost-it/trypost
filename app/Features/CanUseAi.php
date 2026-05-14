<?php

declare(strict_types=1);

namespace App\Features;

use App\Enums\Plan\Slug;
use App\Models\Account;

class CanUseAi
{
    public string $name = 'can-use-ai';

    public function resolve(Account $scope): bool
    {
        return match ($scope->plan?->slug) {
            Slug::Free => false,
            Slug::Starter, Slug::Plus, Slug::Pro, Slug::Max => true,
            default => false,
        };
    }
}
