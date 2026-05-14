<?php

declare(strict_types=1);

namespace App\Actions\Invite;

use App\Models\User;
use App\Models\Workspace;

class RemoveMember
{
    public static function execute(Workspace $workspace, string $userId): void
    {
        $workspace->members()->detach($userId);

        User::where('id', $userId)
            ->where('current_workspace_id', $workspace->id)
            ->update(['current_workspace_id' => null]);
    }
}
