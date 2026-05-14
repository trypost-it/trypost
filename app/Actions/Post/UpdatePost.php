<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Enums\Post\Action as PostAction;
use App\Enums\Post\Status as PostStatus;
use App\Features\ScheduledPostsLimit;
use App\Jobs\PublishPost;
use App\Models\Post;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

class UpdatePost
{
    /**
     * @return array{post: Post, action: PostAction|null}
     */
    public static function execute(Workspace $workspace, Post $post, array $data): array
    {
        if ($post->status === PostStatus::Published) {
            return ['post' => $post, 'action' => PostAction::AlreadyPublished];
        }

        $scheduledAt = $post->scheduled_at;
        if (data_get($data, 'scheduled_at')) {
            $scheduledAt = Carbon::parse(data_get($data, 'scheduled_at'))->utc();
        }

        $rawStatus = data_get($data, 'status');
        $status = $rawStatus instanceof PostStatus
            ? $rawStatus
            : ($rawStatus !== null ? PostStatus::from($rawStatus) : $post->status);

        if ($status === PostStatus::Scheduled && $scheduledAt && $scheduledAt->isFuture()) {
            $limit = Feature::for($workspace->account)->value(ScheduledPostsLimit::class);

            if ($limit !== null) {
                $current = Post::where('workspace_id', $workspace->id)
                    ->where('status', PostStatus::Scheduled)
                    ->where('scheduled_at', '>', now())
                    ->when(
                        $post->status === PostStatus::Scheduled,
                        fn ($q) => $q->where('id', '!=', $post->id),
                    )
                    ->count();

                if ($current >= $limit) {
                    abort(response()->json([
                        'message' => __('billing.flash.scheduled_post_limit', ['limit' => $limit]),
                        'upgrade_required' => true,
                        'reason' => 'scheduled_post_limit',
                        'limit' => $limit,
                        'current' => $current,
                    ], Response::HTTP_PAYMENT_REQUIRED));
                }
            }
        }

        $post->update([
            'content' => data_get($data, 'content', $post->content),
            'media' => data_get($data, 'media', $post->media),
            'status' => $status === PostStatus::Publishing ? PostStatus::Publishing : $status,
            'scheduled_at' => $scheduledAt,
        ]);

        if (Arr::has($data, 'label_ids')) {
            $post->labels()->sync(data_get($data, 'label_ids', []));
        }

        if (Arr::has($data, 'platforms')) {
            DB::transaction(function () use ($post, $data) {
                $post->postPlatforms()->update(['enabled' => false]);

                foreach (data_get($data, 'platforms', []) as $platformData) {
                    $updateData = ['enabled' => true];

                    if (data_get($platformData, 'content_type') !== null) {
                        $updateData['content_type'] = data_get($platformData, 'content_type');
                    }

                    if (data_get($platformData, 'meta') !== null) {
                        $postPlatform = $post->postPlatforms()->where('id', data_get($platformData, 'id'))->first();

                        if ($postPlatform) {
                            $updateData['meta'] = array_merge($postPlatform->meta ?? [], data_get($platformData, 'meta'));
                        }
                    }

                    $post->postPlatforms()
                        ->where('id', data_get($platformData, 'id'))
                        ->update($updateData);
                }
            });
        }

        if ($status === PostStatus::Publishing) {
            $post->update(['scheduled_at' => now()]);
            PublishPost::dispatch($post);

            return ['post' => $post, 'action' => PostAction::Publishing];
        }

        if ($status === PostStatus::Scheduled) {
            return ['post' => $post, 'action' => PostAction::Scheduled];
        }

        return ['post' => $post, 'action' => null];
    }
}
