<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\PostPlatform;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostPlatformStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PostPlatform $postPlatform) {}

    public function broadcastAs(): string
    {
        return 'post.platform.status.updated';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("post.{$this->postPlatform->post_id}"),
            new PrivateChannel("workspace.{$this->postPlatform->post->workspace_id}"),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return [
            'post_id' => $this->postPlatform->post_id,
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
