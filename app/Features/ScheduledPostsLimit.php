<?php

declare(strict_types=1);

namespace App\Features;

use App\Enums\Plan\Slug;
use App\Models\Account;

class ScheduledPostsLimit
{
    public string $name = 'scheduled-posts-limit';

    public function resolve(Account $scope): ?int
    {
        return match ($scope->plan?->slug) {
            Slug::Free => 15,
            default => null,
        };
    }
}
