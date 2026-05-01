<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Enums\Ai\Orientation;
use App\Enums\Ai\UsageType;
use App\Exceptions\Ai\QuotaExhaustedException;
use App\Features\AiVideosLimit;
use App\Models\AiUsageLog;
use App\Models\Media;
use App\Models\Workspace;
use App\Services\Ai\VideoGenerationService;
use Laravel\Pennant\Feature;

final class GenerateVideo
{
    /**
     * Generate an AI video, store it, register it, and log the usage.
     *
     * @throws QuotaExhaustedException When the workspace's monthly video quota is exhausted.
     */
    public static function execute(
        Workspace $workspace,
        string $prompt,
        Orientation $orientation,
        ?string $userId = null,
        ?string $postId = null,
    ): Media {
        $limit = (int) Feature::for($workspace->account)->value(AiVideosLimit::class);
        $used = AiUsageLog::monthlyCount($workspace->account_id, UsageType::Video);

        if ($used >= $limit) {
            throw new QuotaExhaustedException(UsageType::Video, $used, $limit);
        }

        return app(VideoGenerationService::class)->generate(
            prompt: $prompt,
            workspace: $workspace,
            userId: $userId,
            postId: $postId,
            orientation: $orientation,
        );
    }
}
