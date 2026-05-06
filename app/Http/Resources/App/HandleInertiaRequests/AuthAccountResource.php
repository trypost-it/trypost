<?php

declare(strict_types=1);

namespace App\Http\Resources\App\HandleInertiaRequests;

use App\Models\Account;

class AuthAccountResource
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Account $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'created_at' => $account->created_at,
        ];
    }
}
