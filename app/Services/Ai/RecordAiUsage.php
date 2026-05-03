<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\Ai\UsageType;
use App\Models\AiUsageLog;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Persists an AI usage row and debits credits from the account's monthly
 * quota. Credits are billed at the account level (Workspace::account_id);
 * workspace_id is recorded for analytics.
 *
 * Wraps the create in a try/catch so a tracking failure NEVER bubbles up
 * and breaks the actual AI flow — at worst we miss a usage row and the
 * user gets unblocked.
 */
final class RecordAiUsage
{
    /**
     * Record a usage entry for a text generation. Credits are computed from
     * total_tokens via CreditCost::forText().
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function recordText(
        Workspace $workspace,
        int $promptTokens,
        int $completionTokens,
        ?string $provider = null,
        ?string $model = null,
        ?string $userId = null,
        ?string $postId = null,
        array $metadata = [],
    ): void {
        $totalTokens = $promptTokens + $completionTokens;
        $credits = CreditCost::forText($totalTokens);

        self::persist(
            workspace: $workspace,
            type: UsageType::Text,
            credits: $credits,
            provider: $provider,
            model: $model,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $totalTokens,
            userId: $userId,
            postId: $postId,
            metadata: $metadata,
        );
    }

    /**
     * Record a usage entry for an image-template generation. Templates do not
     * call an LLM (composed via Unsplash + branding) so we charge zero credits.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function recordTemplate(
        Workspace $workspace,
        ?string $provider = null,
        ?string $userId = null,
        ?string $postId = null,
        array $metadata = [],
    ): void {
        self::persist(
            workspace: $workspace,
            type: UsageType::Template,
            credits: 0,
            provider: $provider,
            model: null,
            promptTokens: 0,
            completionTokens: 0,
            totalTokens: 0,
            userId: $userId,
            postId: $postId,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private static function persist(
        Workspace $workspace,
        UsageType $type,
        int $credits,
        ?string $provider,
        ?string $model,
        int $promptTokens,
        int $completionTokens,
        int $totalTokens,
        ?string $userId,
        ?string $postId,
        array $metadata,
    ): void {
        try {
            AiUsageLog::create([
                'account_id' => $workspace->account_id,
                'workspace_id' => $workspace->id,
                'user_id' => $userId,
                'post_id' => $postId,
                'type' => $type,
                'provider' => $provider,
                'model' => $model,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
                'credits' => $credits,
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
