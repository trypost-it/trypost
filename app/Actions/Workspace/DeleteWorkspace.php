<?php

declare(strict_types=1);

namespace App\Actions\Workspace;

use App\Models\User;
use App\Models\Workspace;

class DeleteWorkspace
{
    public static function execute(User $user, Workspace $workspace): void
    {
        User::where('current_workspace_id', $workspace->id)->update(['current_workspace_id' => null]);

        if (! config('trypost.self_hosted') && $workspace->subscribed(Workspace::SUBSCRIPTION_NAME)) {
            $workspace->subscription(Workspace::SUBSCRIPTION_NAME)->cancel();
        }

        $workspace->delete();
    }
}
