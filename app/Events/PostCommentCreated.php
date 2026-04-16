<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\PostComment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class PostCommentCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public PostComment $comment) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('post.'.$this->comment->post_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'comment' => [
                'id' => $this->comment->id,
                'user_id' => $this->comment->user_id,
                'parent_id' => $this->comment->parent_id,
                'body' => $this->comment->body,
                'reactions' => $this->comment->reactions ?? [],
                'created_at' => $this->comment->created_at->toISOString(),
                'updated_at' => $this->comment->updated_at->toISOString(),
                'user' => [
                    'id' => $this->comment->user->id,
                    'name' => $this->comment->user->name,
                    'photo_url' => $this->comment->user->photo_url,
                ],
            ],
        ];
    }
}
