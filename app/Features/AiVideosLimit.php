<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Workspace;

class AiVideosLimit
{
    public function resolve(Workspace $scope): int
    {
        return $scope->plan?->ai_videos_limit ?? 10;
    }
}
