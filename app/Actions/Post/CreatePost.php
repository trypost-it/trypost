<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Enums\PostPlatform\Status as PostPlatformStatus;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;

class CreatePost
{
    public static function execute(Workspace $workspace, User $user, array $data): Post
    {
        $date = data_get($data, 'date') ?: Carbon::now($workspace->timezone)->format('Y-m-d');
        $scheduledAt = Carbon::parse($date, $workspace->timezone)
            ->setTime(9, 0)
            ->utc();

        $post = $workspace->posts()->create([
            'user_id' => $user->id,
            'content' => data_get($data, 'content', ''),
            'media' => data_get($data, 'media', []),
            'status' => PostStatus::Draft,
            'scheduled_at' => $scheduledAt,
        ]);

        $socialAccounts = $workspace->socialAccounts()->active()->get();

        foreach ($socialAccounts as $account) {
            $post->postPlatforms()->create([
                'social_account_id' => $account->id,
                'platform' => $account->platform->value,
                'platform_name' => $account->display_name,
                'platform_username' => $account->username,
                'platform_avatar' => $account->getRawOriginal('avatar_url'),
                'content_type' => ContentType::defaultFor($account->platform),
                'status' => PostPlatformStatus::Pending,
                'enabled' => false,
            ]);
        }

        return $post;
    }
}
