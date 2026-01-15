<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishPost implements ShouldQueue
{
    use Queueable;

    public function __construct(public Post $post) {}

    public function handle(): void
    {
        $this->post->markAsPublishing();

        foreach ($this->post->postPlatforms()->where('enabled', true)->get() as $postPlatform) {
            PublishToSocialPlatform::dispatch($postPlatform);
        }
    }
}
