<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * Resolves the credit cost of an AI action from config/ai-credits.php.
 *
 * Text scales by tokens (ceil(total_tokens / tokens_per_credit)), while
 * images and videos are flat per call because providers charge per output.
 */
final class CreditCost
{
    public static function forText(int $totalTokens): int
    {
        $tokensPerCredit = (int) config('ai-credits.text.tokens_per_credit', 150);

        if ($tokensPerCredit <= 0 || $totalTokens <= 0) {
            return 0;
        }

        return (int) ceil($totalTokens / $tokensPerCredit);
    }

    public static function forImage(?string $model = null): int
    {
        $models = (array) config('ai-credits.image', []);

        if ($model !== null && isset($models[$model])) {
            return (int) $models[$model];
        }

        return (int) ($models['default'] ?? 0);
    }

    public static function forVideo(?string $model = null): int
    {
        $models = (array) config('ai-credits.video', []);

        if ($model !== null && isset($models[$model])) {
            return (int) $models[$model];
        }

        return (int) ($models['default'] ?? 0);
    }
}
