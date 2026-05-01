<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Enums\Ai\Orientation;
use App\Enums\Ai\UsageType;
use App\Enums\Media\Type as MediaType;
use App\Exceptions\Ai\QuotaExhaustedException;
use App\Features\AiImagesLimit;
use App\Models\AiUsageLog;
use App\Models\Media;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Image;
use Laravel\Pennant\Feature;

final class GenerateImage
{
    /**
     * Generate an AI image, store it, register it in the workspace media library,
     * and log the usage. Returns the persisted Media model.
     *
     * Throws QuotaExhaustedException when the workspace's monthly image quota
     * is exhausted, leaving the caller free to translate that into a tool message,
     * an HTTP error, etc.
     */
    public static function execute(
        Workspace $workspace,
        string $prompt,
        Orientation $orientation,
        ?string $userId = null,
        ?string $postId = null,
    ): Media {
        $limit = (int) Feature::for($workspace->account)->value(AiImagesLimit::class);
        $used = AiUsageLog::monthlyCount($workspace->account_id, UsageType::Image);

        if ($used >= $limit) {
            throw new QuotaExhaustedException(UsageType::Image, $used, $limit);
        }

        $renderedPrompt = view('prompts.assistant.image', [
            'prompt' => $prompt,
            'brand_name' => $workspace->name ?? '',
            'tone' => $workspace->brand_tone ?? 'professional',
            'aspect_ratio' => $orientation->aspectRatio(),
            'content_language' => $workspace->content_language ?? 'en',
        ])->render();

        $response = Image::of($renderedPrompt)
            ->size($orientation->imageApiSize())
            ->quality('high')
            ->generate();

        $storedPath = $response->store('medias');

        $media = $workspace->media()->create([
            'group_id' => Str::uuid()->toString(),
            'collection' => 'assets',
            'type' => MediaType::Image,
            'path' => $storedPath,
            'original_filename' => 'ai-generated.png',
            'mime_type' => 'image/png',
            'size' => Storage::size($storedPath),
            'order' => 0,
            'meta' => ['ai_generated' => true, 'prompt' => Str::limit($prompt, 200)],
        ]);

        AiUsageLog::create([
            'account_id' => $workspace->account_id,
            'workspace_id' => $workspace->id,
            'user_id' => $userId,
            'post_id' => $postId,
            'type' => UsageType::Image,
            'provider' => (string) config('ai.default_for_images'),
        ]);

        return $media;
    }
}
