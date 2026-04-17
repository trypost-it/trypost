<?php

declare(strict_types=1);

namespace App\Jobs\Ai;

use App\Ai\Agents\SocialMediaAssistant;
use App\Ai\Tools\AttachmentCollector;
use App\Enums\Ai\Intent;
use App\Enums\Ai\UsageType;
use App\Enums\AiMessage\Status;
use App\Events\Ai\AssistantMessageUpdated;
use App\Features\AiImagesLimit;
use App\Features\AiVideosLimit;
use App\Models\AiMessage;
use App\Models\AiUsageLog;
use App\Services\Ai\HumanizerService;
use App\Services\Ai\IntentDetector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;
use RuntimeException;
use Throwable;

class GenerateAssistantResponse implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public AiMessage $assistantMessage,
        public string $prompt,
        public string $intent,
    ) {
        $this->onQueue('ai');
    }

    public function handle(IntentDetector $intentDetector, AttachmentCollector $collector, HumanizerService $humanizer): void
    {
        $this->assistantMessage->update(['status' => Status::Generating]);

        AssistantMessageUpdated::dispatch($this->assistantMessage);

        $post = $this->assistantMessage->post()->with('postPlatforms')->firstOrFail();
        $workspace = $post->workspace;

        $intent = Intent::tryFrom($this->intent) ?? Intent::Text;

        $assistantMessages = $post->aiMessages()
            ->where('role', 'assistant')
            ->where('id', '!=', $this->assistantMessage->id)
            ->get();

        $imagesInThread = $assistantMessages->sum(
            fn ($m) => collect($m->attachments ?? [])->where('type', 'image')->count()
        );

        $videosInThread = $assistantMessages->sum(
            fn ($m) => collect($m->attachments ?? [])->where('type', 'video')->count()
        );

        $imageLimit = (int) Feature::for($workspace->account)->value(AiImagesLimit::class);
        $imageUsed = AiUsageLog::monthlyCount($workspace->account_id, UsageType::Image);
        $imageRemaining = max(0, $imageLimit - $imageUsed);

        $videoLimit = (int) Feature::for($workspace->account)->value(AiVideosLimit::class);
        $videoUsed = AiUsageLog::monthlyCount($workspace->account_id, UsageType::Video);
        $videoRemaining = max(0, $videoLimit - $videoUsed);

        $stateContext = sprintf(
            "[Session state — use this to track progress and respect quotas]\n".
            "- Images already generated in this conversation: %d\n".
            "- Videos already generated in this conversation: %d\n".
            "- Monthly quota remaining: %d images, %d videos\n",
            $imagesInThread,
            $videosInThread,
            $imageRemaining,
            $videoRemaining,
        );

        $promptWithState = "{$stateContext}\n{$this->prompt}";

        $collector->clear();

        $response = (new SocialMediaAssistant(
            workspace: $workspace,
            post: $post,
            userId: $this->assistantMessage->user_id,
        ))->prompt($promptWithState);

        $responseContent = (string) ($response['message'] ?? $response->text ?? '');
        $quickActions = $response['quick_actions'] ?? [];

        $attachments = $collector->all();

        // Only humanize actual post captions (turns that generated media).
        // Conversational turns (greetings, questions, plan summaries) skip
        // the humanizer to preserve the agent's natural short responses.
        if (! empty($attachments)) {
            $responseContent = $humanizer->humanize($responseContent, $workspace);
        }

        $generatedIntent = $intent->value;
        foreach ($attachments as $attachment) {
            if (isset($attachment['type'])) {
                $generatedIntent = $attachment['type'];
                break;
            }
        }

        $this->assistantMessage->update([
            'content' => $responseContent,
            'attachments' => $attachments,
            'status' => Status::Completed,
            'metadata' => array_merge(
                $this->assistantMessage->metadata ?? [],
                ['intent' => $generatedIntent, 'quick_actions' => $quickActions],
            ),
        ]);

        AssistantMessageUpdated::dispatch($this->assistantMessage);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('GenerateAssistantResponse job failed', [
            'assistant_message_id' => $this->assistantMessage->id,
            'error' => $exception?->getMessage(),
        ]);

        $errorMessage = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : __('assistant.error');

        $this->assistantMessage->update([
            'content' => $errorMessage,
            'status' => Status::Failed,
            'error_message' => $exception?->getMessage(),
            'metadata' => array_merge(
                $this->assistantMessage->metadata ?? [],
                ['error' => true],
            ),
        ]);

        AssistantMessageUpdated::dispatch($this->assistantMessage);
    }
}
