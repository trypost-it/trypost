<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateUser
{
    /**
     * @param  array{name: string, email: string, password?: string, email_verified_at?: \DateTimeInterface|null, is_invite?: bool}  $data
     */
    public static function execute(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $isInviteRegistration = data_get($data, 'is_invite', false);

            $account = Account::create([
                'name' => data_get($data, 'name')."'s Account",
                'billing_email' => data_get($data, 'email'),
            ]);

            $user = User::create([
                'name' => data_get($data, 'name'),
                'email' => data_get($data, 'email'),
                'password' => data_get($data, 'password'),
                'email_verified_at' => data_get($data, 'email_verified_at', $isInviteRegistration ? now() : null),
                'account_id' => $account->id,
            ]);

            $account->update(['owner_id' => $user->id]);

            return $user;
        });
    }
}
