<?php

declare(strict_types=1);

namespace App\Events\Ai;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostCreationReady implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $userId,
        public string $creationId,
        public ?string $content,
        public ?string $error = null,
        public ?string $imageTitle = null,
        public ?string $imageBody = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("users.{$this->userId}.ai-creation.{$this->creationId}");
    }

    public function broadcastAs(): string
    {
        return 'PostCreationReady';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'creation_id' => $this->creationId,
            'content' => $this->content,
            'image_title' => $this->imageTitle,
            'image_body' => $this->imageBody,
            'error' => $this->error,
        ];
    }
}
