<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Enums\Post\Status as PostStatus;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;

class CreatePost
{
    public static function execute(Workspace $workspace, User $user, array $data): Post
    {
        $date = data_get($data, 'date') ?: Carbon::now('UTC')->format('Y-m-d');
        $scheduledAt = Carbon::parse($date, 'UTC')
            ->setTime(9, 0)
            ->utc();

        $post = $workspace->posts()->create([
            'user_id' => $user->id,
            'content' => data_get($data, 'content', ''),
            'media' => data_get($data, 'media', []),
            'status' => PostStatus::Draft,
            'scheduled_at' => $scheduledAt,
        ]);

        SyncPostPlatforms::execute($post);

        return $post;
    }
}
