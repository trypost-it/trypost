<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Account;

class SocialAccountLimit
{
    public function resolve(Account $scope): int
    {
        return $scope->plan?->social_account_limit ?? 5;
    }
}
