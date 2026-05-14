<?php

declare(strict_types=1);

namespace App\Features;

use App\Enums\Plan\Slug;
use App\Models\Account;

class CanUseAnalytics
{
    public string $name = 'can-use-analytics';

    public function resolve(Account $scope): bool
    {
        return match ($scope->plan?->slug) {
            Slug::Free => false,
            Slug::Starter, Slug::Plus, Slug::Pro, Slug::Max => true,
            default => false,
        };
    }
}
