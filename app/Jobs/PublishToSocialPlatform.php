<?php

namespace App\Jobs;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Events\PostPlatformStatusUpdated;
use App\Exceptions\TokenExpiredException;
use App\Models\PostPlatform;
use App\Services\Social\BlueskyPublisher;
use App\Services\Social\FacebookPublisher;
use App\Services\Social\InstagramPublisher;
use App\Services\Social\LinkedInPagePublisher;
use App\Services\Social\LinkedInPublisher;
use App\Services\Social\PinterestPublisher;
use App\Services\Social\ThreadsPublisher;
use App\Services\Social\TikTokPublisher;
use App\Services\Social\XPublisher;
use App\Services\Social\YouTubePublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PublishToSocialPlatform implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public PostPlatform $postPlatform) {}

    public function handle(): void
    {
        $this->postPlatform->markAsPublishing();
        $this->broadcastStatus();

        try {
            $publisher = $this->getPublisher();
            $result = $publisher->publish($this->postPlatform);

            $this->postPlatform->markAsPublished($result['id'], $result['url'] ?? null);
        } catch (TokenExpiredException $e) {
            Log::error('Token expired while publishing to social platform', [
                'post_platform_id' => $this->postPlatform->id,
                'platform' => $this->postPlatform->platform->value,
                'error' => $e->getMessage(),
                'platform_error_code' => $e->platformErrorCode,
            ]);

            $this->postPlatform->markAsFailed($e->getMessage());
            $this->postPlatform->socialAccount->markAsDisconnected($e->getMessage());
        } catch (\Exception $e) {
            Log::error('Failed to publish to social platform', [
                'post_platform_id' => $this->postPlatform->id,
                'platform' => $this->postPlatform->platform->value,
                'error' => $e->getMessage(),
            ]);

            $this->postPlatform->markAsFailed($e->getMessage());
        }

        // Always check and update post status after each platform finishes
        $this->updatePostStatus();

        // Broadcast final status
        $this->broadcastStatus();
    }

    private function broadcastStatus(): void
    {
        PostPlatformStatusUpdated::dispatch($this->postPlatform->fresh());
    }

    private function getPublisher(): LinkedInPublisher|LinkedInPagePublisher|XPublisher|TikTokPublisher|YouTubePublisher|FacebookPublisher|InstagramPublisher|ThreadsPublisher|PinterestPublisher|BlueskyPublisher
    {
        return match ($this->postPlatform->platform) {
            SocialPlatform::LinkedIn => app(LinkedInPublisher::class),
            SocialPlatform::LinkedInPage => app(LinkedInPagePublisher::class),
            SocialPlatform::X => app(XPublisher::class),
            SocialPlatform::TikTok => app(TikTokPublisher::class),
            SocialPlatform::YouTube => app(YouTubePublisher::class),
            SocialPlatform::Facebook => app(FacebookPublisher::class),
            SocialPlatform::Instagram => app(InstagramPublisher::class),
            SocialPlatform::Threads => app(ThreadsPublisher::class),
            SocialPlatform::Pinterest => app(PinterestPublisher::class),
            SocialPlatform::Bluesky => app(BlueskyPublisher::class),
        };
    }

    private function updatePostStatus(): void
    {
        $post = $this->postPlatform->post->fresh();
        $enabledPlatforms = $post->postPlatforms->where('enabled', true);

        $total = $enabledPlatforms->count();
        $publishedCount = $enabledPlatforms->where('status', 'published')->count();
        $failedCount = $enabledPlatforms->where('status', 'failed')->count();
        $finishedCount = $publishedCount + $failedCount;

        // Only update post status when all platforms have finished
        if ($finishedCount < $total) {
            return;
        }

        if ($publishedCount === $total) {
            $post->markAsPublished();
        } elseif ($publishedCount > 0) {
            $post->markAsPartiallyPublished();
        } else {
            $post->markAsFailed();
        }
    }
}
