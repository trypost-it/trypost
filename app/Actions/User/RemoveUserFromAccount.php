<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RemoveUserFromAccount
{
    public static function execute(Account $account, User $user): void
    {
        if ($user->id === $account->owner_id) {
            throw new \DomainException('Cannot remove the account owner.');
        }

        if ($user->account_id !== $account->id) {
            throw new \DomainException('User does not belong to this account.');
        }

        DB::transaction(function () use ($user) {
            $user->workspaces()->detach();
            $user->delete();
        });
    }
}
