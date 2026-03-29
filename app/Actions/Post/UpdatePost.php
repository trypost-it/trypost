<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Enums\Post\Status as PostStatus;
use App\Jobs\PublishPost;
use App\Models\Post;
use App\Models\Workspace;
use Carbon\Carbon;

class UpdatePost
{
    /**
     * @return array{post: Post, action: string|null}
     */
    public static function execute(Workspace $workspace, Post $post, array $data): array
    {
        if ($post->status === PostStatus::Published) {
            return ['post' => $post, 'action' => 'already_published'];
        }

        $scheduledAt = $post->scheduled_at;
        if (data_get($data, 'scheduled_at')) {
            $scheduledAt = Carbon::parse(data_get($data, 'scheduled_at'), $workspace->timezone)->utc();
        }

        $status = data_get($data, 'status', $post->status);

        $post->update([
            'status' => $status === 'publishing' ? PostStatus::Publishing : $status,
            'synced' => data_get($data, 'synced', $post->synced),
            'scheduled_at' => $scheduledAt,
        ]);

        if (array_key_exists('label_ids', $data)) {
            $post->labels()->sync(data_get($data, 'label_ids', []));
        }

        $post->postPlatforms()->update(['enabled' => false]);

        foreach (data_get($data, 'platforms', []) as $platformData) {
            $updateData = [
                'enabled' => true,
                'content' => data_get($platformData, 'content'),
            ];

            if (data_get($platformData, 'content_type') !== null) {
                $updateData['content_type'] = data_get($platformData, 'content_type');
            }

            if (isset($platformData['meta'])) {
                $postPlatform = $post->postPlatforms()->where('id', data_get($platformData, 'id'))->first();
                $updateData['meta'] = array_merge($postPlatform->meta ?? [], $platformData['meta']);
            }

            $post->postPlatforms()
                ->where('id', data_get($platformData, 'id'))
                ->update($updateData);
        }

        if ($status === 'publishing') {
            $post->update(['scheduled_at' => now()]);
            PublishPost::dispatch($post);

            return ['post' => $post, 'action' => 'publishing'];
        }

        if ($status === 'scheduled') {
            return ['post' => $post, 'action' => 'scheduled'];
        }

        return ['post' => $post, 'action' => null];
    }
}
