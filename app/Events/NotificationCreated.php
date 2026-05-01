<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Notification $notification) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('workspace.'.$this->notification->workspace_id.'.user.'.$this->notification->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'NotificationCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => [
                'id' => $this->notification->id,
                'user_id' => $this->notification->user_id,
                'workspace_id' => $this->notification->workspace_id,
                'type' => $this->notification->type->value,
                'channel' => $this->notification->channel->value,
                'title' => $this->notification->title,
                'body' => $this->notification->body,
                'data' => $this->notification->data,
                'read_at' => $this->notification->read_at?->toISOString(),
                'archived_at' => $this->notification->archived_at?->toISOString(),
                'created_at' => $this->notification->created_at->toISOString(),
                'updated_at' => $this->notification->updated_at->toISOString(),
            ],
        ];
    }
}
