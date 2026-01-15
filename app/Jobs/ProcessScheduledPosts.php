<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessScheduledPosts implements ShouldQueue
{
    use Queueable;

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
