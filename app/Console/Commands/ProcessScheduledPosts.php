<?php

namespace App\Console\Commands;

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
            ->with(['postPlatforms.socialAccount', 'postPlatforms.media'])
            ->chunk(100, function ($posts) {
                foreach ($posts as $post) {
                    PublishPost::dispatch($post);
                }
            });
    }
}
