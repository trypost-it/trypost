<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Account;

class MemberLimit
{
    public string $name = 'member-limit';

    public function resolve(Account $scope): int
    {
        return $scope->plan?->member_limit ?? 1;
    }
}
