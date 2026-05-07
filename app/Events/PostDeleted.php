<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class PostDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $postId,
        public string $workspaceId,
    ) {}

    public function broadcastAs(): string
    {
        return 'post.deleted';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('workspace.'.$this->workspaceId),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return [
            'post_id' => $this->postId,
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
