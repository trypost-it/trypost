<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\Ai\UsageType;
use App\Models\AiUsageLog;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin helper that writes a row to `workspace_ai_usages` whenever an AI
 * action runs (image generation, LLM call, etc). Wraps the create in a
 * try/catch so a tracking failure NEVER bubbles up and breaks the actual
 * AI flow — at worst we miss a usage row and the user gets unblocked.
 */
final class RecordAiUsage
{
    /**
     * Record a usage entry. The Workspace carries the account_id implicitly,
     * which is the FK that drives quotas (account-level billing).
     *
     * @param  array<string, mixed>  $metadata  Free-form context (model, format, agent, etc.)
     */
    public static function record(
        Workspace $workspace,
        UsageType $type,
        ?string $provider = null,
        ?string $userId = null,
        ?string $postId = null,
        array $metadata = [],
    ): void {
        try {
            AiUsageLog::create([
                'account_id' => $workspace->account_id,
                'workspace_id' => $workspace->id,
                'user_id' => $userId,
                'post_id' => $postId,
                'type' => $type,
                'provider' => $provider,
                'metadata' => $metadata !== [] ? $metadata : null,
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to record AI usage', [
                'workspace_id' => $workspace->id,
                'type' => $type->value,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
