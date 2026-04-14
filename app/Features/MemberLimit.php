<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Workspace;

class MemberLimit
{
    public function resolve(Workspace $scope): int
    {
        return $scope->plan?->member_limit ?? 1;
    }
}
