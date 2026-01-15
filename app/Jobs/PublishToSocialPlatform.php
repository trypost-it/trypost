<?php

namespace App\Jobs;

use App\Enums\PostStatus;
use App\Enums\SocialPlatform;
use App\Models\PostPlatform;
use App\Services\Social\LinkedInPagePublisher;
use App\Services\Social\LinkedInPublisher;
use App\Services\Social\TikTokPublisher;
use App\Services\Social\XPublisher;
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

        try {
            $publisher = $this->getPublisher();
            $result = $publisher->publish($this->postPlatform);

            $this->postPlatform->markAsPublished($result['id'], $result['url'] ?? null);

            $this->checkPostComplete();
        } catch (\Exception $e) {
            Log::error('Failed to publish to social platform', [
                'post_platform_id' => $this->postPlatform->id,
                'platform' => $this->postPlatform->platform->value,
                'error' => $e->getMessage(),
            ]);

            $this->postPlatform->markAsFailed($e->getMessage());
            $this->postPlatform->post->markAsFailed();

            throw $e;
        }
    }

    private function getPublisher(): LinkedInPublisher|LinkedInPagePublisher|XPublisher|TikTokPublisher
    {
        return match ($this->postPlatform->platform) {
            SocialPlatform::LinkedIn => app(LinkedInPublisher::class),
            SocialPlatform::LinkedInPage => app(LinkedInPagePublisher::class),
            SocialPlatform::X => app(XPublisher::class),
            SocialPlatform::TikTok => app(TikTokPublisher::class),
        };
    }

    private function checkPostComplete(): void
    {
        $post = $this->postPlatform->post->fresh();

        $allPublished = $post->postPlatforms->every(fn ($pp) => $pp->status === 'published');

        if ($allPublished) {
            $post->markAsPublished();
        }
    }
}
