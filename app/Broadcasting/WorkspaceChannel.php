<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\User;
use App\Models\Workspace;

class WorkspaceChannel
{
    public function join(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user);
    }
}
