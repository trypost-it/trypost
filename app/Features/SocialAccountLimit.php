<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Workspace;

class SocialAccountLimit
{
    public function resolve(Workspace $scope): int
    {
        return $scope->plan?->social_account_limit ?? 5;
    }
}
