<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\User;

class UserAiCreationChannel
{
    public function join(User $user, string $userId, string $creationId): bool
    {
        return $user->id === $userId;
    }
}
