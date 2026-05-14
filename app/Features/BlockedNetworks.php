<?php

declare(strict_types=1);

namespace App\Features;

use App\Enums\Plan\Slug;
use App\Models\Account;

class BlockedNetworks
{
    public string $name = 'blocked-networks';

    /**
     * @return array<int, string> empty array = nothing blocked
     */
    public function resolve(Account $scope): array
    {
        return match ($scope->plan?->slug) {
            Slug::Free => ['x'],
            default => [],
        };
    }
}
