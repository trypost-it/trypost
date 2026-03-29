<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Models\Post;

class DeletePost
{
    public static function execute(Post $post): void
    {
        $post->delete();
    }
}
