<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Notification\Channel;
use App\Enums\Notification\Type;
use App\Enums\PostPlatform\Status as PostPlatformStatus;
use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\SocialAccount\Status;
use App\Events\PostPlatformStatusUpdated;
use App\Exceptions\Social\SocialPublishException;
use App\Exceptions\TokenExpiredException;
use App\Mail\PostPublished;
use App\Mail\PostPublishFailed;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Services\Social\BlueskyPublisher;
use App\Services\Social\ConnectionVerifier;
use App\Services\Social\FacebookPublisher;
use App\Services\Social\InstagramPublisher;
use App\Services\Social\LinkedInPagePublisher;
use App\Services\Social\LinkedInPublisher;
use App\Services\Social\MastodonPublisher;
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

    public int $tries = 1;

    public int $timeout = 600; // 10 minutes — large video uploads need time

    public function __construct(public PostPlatform $postPlatform)
    {
        $this->onQueue($postPlatform->platform->queue());
    }

    public function handle(): void
    {
        // Idempotency: skip if already published (prevents duplicate posts on retry)
        $this->postPlatform->refresh();

        if ($this->postPlatform->status === PostPlatformStatus::Published) {
            return;
        }

        if (! $this->postPlatform->socialAccount->is_active) {
            $this->postPlatform->markAsFailed(__('posts.errors.account_inactive'));
            $this->updatePostStatus();
            $this->broadcastStatus();

            return;
        }

        if ($this->postPlatform->socialAccount->status === Status::Disconnected) {
            $this->postPlatform->markAsFailed(__('posts.errors.account_disconnected'));
            $this->updatePostStatus();
            $this->broadcastStatus();

            return;
        }

        $requiredScopes = $this->postPlatform->platform->requiredPublishScopes();
        $accountScopes = $this->postPlatform->socialAccount->scopes ?? [];

        if (! empty($requiredScopes)) {
            $missingScopes = array_diff($requiredScopes, $accountScopes);

            if (! empty($missingScopes)) {
                $this->postPlatform->markAsFailed(
                    'Missing permissions: '.implode(', ', $missingScopes).'. Please reconnect your account.',
                    ['category' => 'permission', 'missing_scopes' => $missingScopes, 'failed_at' => now()->toIso8601String()]
                );
                $this->updatePostStatus();
                $this->broadcastStatus();

                return;
            }
        }

        $this->postPlatform->markAsPublishing();
        $this->broadcastStatus();

        $maxAttempts = 2; // Original attempt + 1 retry after token refresh

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $publisher = $this->getPublisher();
                $result = $publisher->publish($this->postPlatform);
                $this->postPlatform->markAsPublished(data_get($result, 'id'), data_get($result, 'url'));
                break;
            } catch (TokenExpiredException $e) {
                if ($attempt < $maxAttempts) {
                    try {
                        $this->refreshAccountToken();

                        continue;
                    } catch (\Throwable $refreshError) {
                        Log::error('Token refresh failed during publish retry', [
                            'post_platform_id' => $this->postPlatform->id,
                            'platform' => $this->postPlatform->platform->value,
                            'error' => $refreshError->getMessage(),
                        ]);
                    }
                }

                // All attempts exhausted or refresh failed
                Log::error('Token expired while publishing to social platform', [
                    'post_platform_id' => $this->postPlatform->id,
                    'platform' => $this->postPlatform->platform->value,
                    'error' => $e->getMessage(),
                    'platform_error_code' => $e->platformErrorCode,
                ]);

                $this->postPlatform->markAsFailed($e->getMessage(), [
                    'category' => 'token_expired',
                    'platform_error_code' => $e->platformErrorCode,
                    'failed_at' => now()->toIso8601String(),
                ]);
                $this->postPlatform->socialAccount->markAsTokenExpired($e->getMessage());
                break;
            } catch (SocialPublishException $e) {
                Log::error('Social publish failed: '.$e->userMessage);
                $this->postPlatform->markAsFailed($e->userMessage, [
                    'category' => $e->category->value,
                    'platform_error_code' => $e->platformErrorCode,
                    'failed_at' => now()->toIso8601String(),
                    'content_length' => mb_strlen($this->postPlatform->post->content ?? ''),
                    'media_count' => count($this->postPlatform->post->media ?? []),
                ]);
                break;
            } catch (\Throwable $e) {
                Log::error('Failed to publish to social platform', [
                    'post_platform_id' => $this->postPlatform->id,
                    'platform' => $this->postPlatform->platform->value,
                    'error' => $e->getMessage(),
                ]);
                $this->postPlatform->markAsFailed($e->getMessage(), [
                    'category' => 'unknown',
                    'failed_at' => now()->toIso8601String(),
                    'content_length' => mb_strlen($this->postPlatform->post->content ?? ''),
                    'media_count' => count($this->postPlatform->post->media ?? []),
                ]);
                break;
            }
        }

        // Always check and update post status after each platform finishes
        $this->updatePostStatus();

        // Broadcast final status
        $this->broadcastStatus();
    }

    private function refreshAccountToken(): void
    {
        $account = $this->postPlatform->socialAccount;

        // Delegate to ConnectionVerifier which already has per-platform refresh logic
        app(ConnectionVerifier::class)->verify($account);
    }

    private function broadcastStatus(): void
    {
        PostPlatformStatusUpdated::dispatch($this->postPlatform->fresh());
    }

    private function getPublisher(): LinkedInPublisher|LinkedInPagePublisher|XPublisher|TikTokPublisher|YouTubePublisher|FacebookPublisher|InstagramPublisher|ThreadsPublisher|PinterestPublisher|BlueskyPublisher|MastodonPublisher
    {
        return match ($this->postPlatform->platform) {
            SocialPlatform::LinkedIn => app(LinkedInPublisher::class),
            SocialPlatform::LinkedInPage => app(LinkedInPagePublisher::class),
            SocialPlatform::X => app(XPublisher::class),
            SocialPlatform::TikTok => app(TikTokPublisher::class),
            SocialPlatform::YouTube => app(YouTubePublisher::class),
            SocialPlatform::Facebook => app(FacebookPublisher::class),
            SocialPlatform::Instagram, SocialPlatform::InstagramFacebook => app(InstagramPublisher::class),
            SocialPlatform::Threads => app(ThreadsPublisher::class),
            SocialPlatform::Pinterest => app(PinterestPublisher::class),
            SocialPlatform::Bluesky => app(BlueskyPublisher::class),
            SocialPlatform::Mastodon => app(MastodonPublisher::class),
        };
    }

    private function updatePostStatus(): void
    {
        $post = $this->postPlatform->post->fresh();
        $enabledPlatforms = $post->postPlatforms->where('enabled', true);

        $total = $enabledPlatforms->count();
        $publishedCount = $enabledPlatforms->where('status', PostPlatformStatus::Published)->count();
        $failedCount = $enabledPlatforms->where('status', PostPlatformStatus::Failed)->count();
        $finishedCount = $publishedCount + $failedCount;

        // Only update post status when all platforms have finished
        if ($finishedCount < $total) {
            return;
        }

        if ($publishedCount === $total) {
            $post->markAsPublished();
            $this->notifySuccess($post);
        } elseif ($publishedCount > 0) {
            $post->markAsPartiallyPublished();
            $this->notifyFailure($post);
        } else {
            $post->markAsFailed();
            $this->notifyFailure($post);
        }
    }

    private function notifySuccess(Post $post): void
    {
        $owner = $post->workspace->owner;

        if (! $owner) {
            return;
        }

        $publishedPlatforms = $post->postPlatforms()
            ->with('socialAccount')
            ->where('enabled', true)
            ->get()
            ->filter(fn ($pp) => $pp->status === PostPlatformStatus::Published)
            ->map(fn ($pp) => $pp->platform->label().' (@'.data_get($pp, 'socialAccount.username', '').')')
            ->implode(', ');

        SendNotification::dispatch(
            user: $owner,
            workspaceId: $post->workspace_id,
            type: Type::PostPublished,
            channel: Channel::Both,
            title: 'Post published successfully',
            body: $publishedPlatforms,
            data: ['post_id' => $post->id],
            mailable: new PostPublished($post),
        );
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('PublishToSocialPlatform job failed permanently', [
            'post_platform_id' => $this->postPlatform->id,
            'platform' => $this->postPlatform->platform->value,
            'error' => $exception?->getMessage(),
        ]);

        $this->postPlatform->refresh();

        if ($this->postPlatform->status !== PostPlatformStatus::Published) {
            $this->postPlatform->markAsFailed($exception?->getMessage() ?? 'Unknown error', [
                'category' => 'job_failed',
                'failed_at' => now()->toIso8601String(),
            ]);
            $this->updatePostStatus();
            $this->broadcastStatus();
        }
    }

    private function notifyFailure(Post $post): void
    {
        $owner = $post->workspace->owner;

        if (! $owner) {
            return;
        }

        $failedPlatforms = $post->postPlatforms()
            ->with('socialAccount')
            ->where('enabled', true)
            ->get()
            ->filter(fn ($pp) => $pp->status === PostPlatformStatus::Failed)
            ->map(fn ($pp) => $pp->platform->label().' (@'.data_get($pp, 'socialAccount.username', '').')')
            ->implode(', ');

        SendNotification::dispatch(
            user: $owner,
            workspaceId: $post->workspace_id,
            type: Type::PostFailed,
            channel: Channel::Both,
            title: 'Post failed to publish',
            body: "Failed on: {$failedPlatforms}",
            data: ['post_id' => $post->id],
            mailable: new PostPublishFailed($post),
        );
    }
}
