<?php

declare(strict_types=1);

namespace App\Actions\SocialAccount;

use App\Models\SocialAccount;

class ToggleSocialAccount
{
    public static function execute(SocialAccount $account): SocialAccount
    {
        $account->update(['is_active' => ! $account->is_active]);

        return $account;
    }
}
