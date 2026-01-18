<?php

namespace App\Enums\WorkspaceInvite;

enum Status: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
}
