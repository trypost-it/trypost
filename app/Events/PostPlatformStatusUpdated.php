<?php

namespace App\Events;

use App\Models\PostPlatform;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostPlatformStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PostPlatform $postPlatform) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('posts.'.$this->postPlatform->post_id),
        ];
    }

    public function broadcastWith(): array
    {
        $this->postPlatform->refresh();
        $post = $this->postPlatform->post->fresh();

        return [
            'post_platform' => [
                'id' => $this->postPlatform->id,
                'status' => $this->postPlatform->status,
                'platform_url' => $this->postPlatform->platform_url,
                'error_message' => $this->postPlatform->error_message,
                'published_at' => $this->postPlatform->published_at?->toISOString(),
            ],
            'post' => [
                'id' => $post->id,
                'status' => $post->status->value,
                'published_at' => $post->published_at?->toISOString(),
            ],
        ];
    }
}
