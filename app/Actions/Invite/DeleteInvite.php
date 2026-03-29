<?php

declare(strict_types=1);

namespace App\Actions\Invite;

use App\Models\WorkspaceInvite;

class DeleteInvite
{
    public static function execute(WorkspaceInvite $invite): void
    {
        $invite->delete();
    }
}
