<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Authorize duplicating a post into the user's current workspace as a
     * fresh draft. The post must live in the user's current workspace and
     * the user must have permission to create posts there.
     */
    public function duplicate(User $user, Post $post): bool
    {
        if ($post->workspace_id !== $user->current_workspace_id) {
            return false;
        }

        return $user->can('createPost', $user->currentWorkspace);
    }
}
