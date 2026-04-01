<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Post\Status as PostStatus;
use App\Jobs\PublishPost;
use App\Models\Post;
use Illuminate\Console\Command;

class ProcessScheduledPosts extends Command
{
    protected $signature = 'posts:process-scheduled';

    protected $description = 'Process scheduled posts that are due for publishing';

    public function handle(): void
    {
        Post::query()
            ->due()
            ->each(function (Post $post) {
                // Atomically claim the post — only dispatch if we successfully change its status
                $claimed = Post::where('id', $post->id)
                    ->where('status', PostStatus::Scheduled)
                    ->update(['status' => PostStatus::Publishing]);

                if ($claimed) {
                    PublishPost::dispatch($post);
                }
            });
    }
}
