<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Events\PostDeleted;
use App\Models\Post;

class DeletePost
{
    public static function execute(Post $post): void
    {
        $postId = $post->id;
        $workspaceId = $post->workspace_id;

        $post->delete();

        PostDeleted::dispatch($postId, $workspaceId);
    }
}
