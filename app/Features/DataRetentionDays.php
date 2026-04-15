<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Account;

class DataRetentionDays
{
    public function resolve(Account $scope): int
    {
        return $scope->plan?->data_retention_days ?? 30;
    }
}
