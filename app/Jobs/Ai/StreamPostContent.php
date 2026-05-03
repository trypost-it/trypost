<?php

declare(strict_types=1);

namespace App\Jobs\Ai;

use App\Ai\Agents\PostContentStreamer;
use App\Models\Workspace;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StreamPostContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $workspaceId,
        public string $userId,
        public string $generationId,
        public string $prompt,
        public ?string $currentContent,
    ) {
        $this->onQueue('ai');
    }

    public function handle(): void
    {
        $workspace = Workspace::findOrFail($this->workspaceId);

        $agent = new PostContentStreamer(
            workspace: $workspace,
            currentContent: $this->currentContent,
        );

        $channel = new PrivateChannel("users.{$this->userId}.ai-gen.{$this->generationId}");

        try {
            $agent->broadcast($this->prompt, $channel, now: true);
        } catch (\Throwable $e) {
            Log::error('PostContentGenerator stream failed', [
                'generation_id' => $this->generationId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
