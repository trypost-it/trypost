<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\User;
use App\Models\Workspace;

class WorkspaceUserChannel
{
    public function join(User $user, Workspace $workspace, User $owner): bool
    {
        return $user->id === $owner->id && $workspace->hasMember($user);
    }
}
