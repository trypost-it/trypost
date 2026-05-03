<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\User;

class UserAiGenerationChannel
{
    public function join(User $user, string $userId, string $generationId): bool
    {
        return $user->id === $userId;
    }
}
