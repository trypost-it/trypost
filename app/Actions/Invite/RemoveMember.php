<?php

declare(strict_types=1);

namespace App\Actions\Invite;

use App\Models\Workspace;

class RemoveMember
{
    public static function execute(Workspace $workspace, string $userId): void
    {
        $workspace->members()->detach($userId);
    }
}
