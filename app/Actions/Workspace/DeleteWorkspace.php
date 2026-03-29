<?php

declare(strict_types=1);

namespace App\Actions\Workspace;

use App\Models\User;
use App\Models\Workspace;

class DeleteWorkspace
{
    public static function execute(User $user, Workspace $workspace): void
    {
        if ($user->current_workspace_id === $workspace->id) {
            $user->update(['current_workspace_id' => null]);
        }

        $workspace->delete();

        if ($user->hasActiveSubscription()) {
            $user->decrementWorkspaceQuantity();
        }
    }
}
