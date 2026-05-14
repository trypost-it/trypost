<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Enums\Plan\Slug;
use App\Jobs\PostHog\SyncUser;
use App\Models\Account;
use App\Models\Plan;
use App\Models\User;
use App\Services\PostHogService;
use Illuminate\Support\Facades\DB;

class CreateUser
{
    /**
     * @param  array{name: string, email: string, password?: string, google_id?: string, github_id?: string, email_verified_at?: \DateTimeInterface|null, is_invite?: bool, registration_ip?: string|null}  $data
     * @param  array<string, string>  $utmParameters
     */
    public static function execute(array $data, array $utmParameters = []): User
    {
        $user = DB::transaction(function () use ($data, $utmParameters): User {
            $isInviteRegistration = data_get($data, 'is_invite', false);

            $freePlanId = Plan::where('slug', Slug::Free)->value('id');

            $account = Account::create([
                'name' => data_get($data, 'name')."'s Account",
                'billing_email' => data_get($data, 'email'),
                'plan_id' => $freePlanId,
            ]);

            $user = User::create(array_merge([
                'name' => data_get($data, 'name'),
                'email' => data_get($data, 'email'),
                'password' => data_get($data, 'password'),
                'google_id' => data_get($data, 'google_id'),
                'github_id' => data_get($data, 'github_id'),
                'email_verified_at' => data_get($data, 'email_verified_at', $isInviteRegistration ? now() : null),
                'account_id' => $account->id,
                'registration_ip' => data_get($data, 'registration_ip'),
            ], $utmParameters));

            $account->update(['owner_id' => $user->id]);

            return $user;
        });

        if (PostHogService::isEnabled()) {
            SyncUser::dispatch((string) $user->id);
        }

        return $user;
    }
}
