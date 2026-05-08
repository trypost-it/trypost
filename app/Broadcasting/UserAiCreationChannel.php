<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\User;

class UserAiCreationChannel
{
    public function join(User $user, User $owner, string $creationId): bool
    {
        return $user->is($owner);
    }
}
