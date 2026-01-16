<?php

namespace App\Broadcasting;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PostChannel
{
    public function join(User $user, Post $post): array|bool
    {
        Log::info('PostChannel::join called', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'post_id' => $post->id,
            'workspace_id' => $post->workspace_id,
        ]);

        $result = $post->workspace->hasMember($user);

        Log::info('PostChannel::join result', [
            'result' => $result,
            'workspace_owner_id' => $post->workspace->user_id,
            'is_owner' => $post->workspace->user_id === $user->id,
        ]);

        return $result;
    }
}
