<?php

declare(strict_types=1);

namespace App\Events\Ai;

use App\Models\AiMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssistantMessageUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AiMessage $message) {}

    public function broadcastAs(): string
    {
        return 'AssistantMessageUpdated';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('post.'.$this->message->post_id),
        ];
    }

    public function broadcastWith(): array
    {
        $this->message->refresh();

        return [
            'message' => [
                'id' => $this->message->id,
                'post_id' => $this->message->post_id,
                'role' => $this->message->role,
                'content' => $this->message->content,
                'content_html' => $this->message->content_html,
                'attachments' => $this->message->attachments,
                'status' => $this->message->status->value,
                'error_message' => $this->message->error_message,
                'metadata' => $this->message->metadata,
                'created_at' => $this->message->created_at->toISOString(),
                'updated_at' => $this->message->updated_at->toISOString(),
            ],
        ];
    }
}
