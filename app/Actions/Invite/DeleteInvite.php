<?php

declare(strict_types=1);

namespace App\Actions\Invite;

use App\Models\Invite;

class DeleteInvite
{
    public static function execute(Invite $invite): void
    {
        $invite->delete();
    }
}
