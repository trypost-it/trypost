<?php

declare(strict_types=1);

namespace App\Ai\Tools;

/**
 * Request-scoped side-channel for tools to surface structured attachments.
 *
 * Laravel AI SDK tools return Stringable|string to the LLM, so they can't
 * pass structured data back to the controller directly. This collector is
 * registered as a scoped singleton — the controller clears it before the
 * agent prompt, tools push attachments into it during execution, and the
 * controller reads $collector->all() after the agent finishes to persist
 * them onto the resulting AiMessage.
 */
class AttachmentCollector
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $attachments = [];

    /**
     * @param  array<string, mixed>  $attachment
     */
    public function push(array $attachment): void
    {
        $this->attachments[] = $attachment;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->attachments;
    }

    public function clear(): void
    {
        $this->attachments = [];
    }
}
