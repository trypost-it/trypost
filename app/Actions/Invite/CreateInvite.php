<?php

declare(strict_types=1);

namespace App\Actions\Invite;

use App\Enums\UserWorkspace\Role as WorkspaceRole;
use App\Mail\WorkspaceInvite as WorkspaceInviteMail;
use App\Models\Workspace;
use App\Models\WorkspaceInvite;
use Illuminate\Support\Facades\Mail;

class CreateInvite
{
    public static function execute(Workspace $workspace, array $data): WorkspaceInvite
    {
        $invite = $workspace->invites()->create([
            'email' => data_get($data, 'email'),
            'role' => data_get($data, 'role', WorkspaceRole::Member),
        ]);

        Mail::to($invite->email)->send(new WorkspaceInviteMail($invite));

        return $invite;
    }
}
