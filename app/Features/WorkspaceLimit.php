<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\Account;

class WorkspaceLimit
{
    public string $name = 'workspace-limit';

    public function resolve(Account $scope): int
    {
        return $scope->plan?->workspace_limit ?? 1;
    }
}
