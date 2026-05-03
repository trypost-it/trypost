<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Account;

class MonthlyCreditsLimit
{
    public string $name = 'monthly-credits-limit';

    public function resolve(Account $scope): int
    {
        return $scope->plan?->monthly_credits_limit ?? 1000;
    }
}
