<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Account;

class ScheduledPostsLimit
{
    public string $name = 'scheduled-posts-limit';

    public function resolve(Account $scope): ?int
    {
        return $scope->plan?->scheduled_posts_limit;
    }
}
