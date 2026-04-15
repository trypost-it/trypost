<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Account;

class AiVideosLimit
{
    public function resolve(Account $scope): int
    {
        return $scope->plan?->ai_videos_limit ?? 10;
    }
}
