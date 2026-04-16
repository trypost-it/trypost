<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\Post;
use App\Models\User;

class PostChannel
{
    public function join(User $user, Post $post): bool
    {
        return $post->workspace->hasMember($user);
    }
}
