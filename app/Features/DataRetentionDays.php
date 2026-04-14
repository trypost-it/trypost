<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Workspace;

class DataRetentionDays
{
    public function resolve(Workspace $scope): int
    {
        return $scope->plan?->data_retention_days ?? 30;
    }
}
