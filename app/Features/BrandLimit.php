<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Workspace;

class BrandLimit
{
    public function resolve(Workspace $scope): int
    {
        return $scope->plan?->brand_limit ?? 0;
    }
}
