<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\Status as PlatformStatus;
use App\Models\Post;
use Illuminate\Console\Command;

class RecoverStuckPosts extends Command
{
    protected $signature = 'social:recover-stuck-posts';

    protected $description = 'Recover posts stuck in publishing status for more than 1 hour';

    public function handle(): void
    {
        $count = 0;

        Post::query()
            ->where('status', PostStatus::Publishing)
            ->where('updated_at', '<=', now()->subHour())
            ->each(function (Post $post) use (&$count) {
                // Mark stuck platforms as failed
                $post->postPlatforms()
                    ->where('enabled', true)
                    ->where('status', PlatformStatus::Publishing)
                    ->where('updated_at', '<=', now()->subHour())
                    ->update([
                        'status' => PlatformStatus::Failed,
                        'error_message' => 'Publishing timed out. Please try again.',
                        'error_context' => json_encode([
                            'category' => 'timeout',
                            'failed_at' => now()->toIso8601String(),
                        ]),
                    ]);

                // Recalculate post status
                $enabledPlatforms = $post->postPlatforms()->where('enabled', true)->get();
                $total = $enabledPlatforms->count();
                $publishedCount = $enabledPlatforms->where('status', PlatformStatus::Published)->count();
                $failedCount = $enabledPlatforms->where('status', PlatformStatus::Failed)->count();

                if ($publishedCount === $total) {
                    $post->markAsPublished();
                } elseif ($publishedCount > 0) {
                    $post->markAsPartiallyPublished();
                } else {
                    $post->markAsFailed();
                }

                $count++;
            });

        $this->info("Recovered {$count} stuck posts.");
    }
}
