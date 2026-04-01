# Publishing Engine Improvements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Improve publishing reliability with rate limit retry, inline token refresh, per-platform concurrency control, and proactive token refresh.

**Architecture:** A shared `HasSocialHttpClient` trait for rate limit retry, a `TokenRefresher` service for centralized refresh logic, Horizon per-platform queues for concurrency, and a scheduled command for proactive refresh.

**Tech Stack:** Laravel 13, PHP 8.4, Horizon, Redis, Pest 4

**Spec:** `docs/superpowers/specs/2026-04-01-publishing-engine-improvements-design.md`

**Scope:** Tasks 1-4 are for implementation now. Tasks 5-6 are documented for future sprints.

---

## NOW — Implement

### Task 1: Rate limit retry (429 handling)

**Files:**
- Create: `app/Services/Social/Concerns/HasSocialHttpClient.php`
- Modify: All 11 publishers to use the trait
- Test: `tests/Unit/Services/Social/Concerns/HasSocialHttpClientTest.php`

- [ ] **Step 1: Write failing test for the trait**

Create test that verifies:
- HTTP 429 response triggers automatic retry (up to 3 times)
- After 3 retries, the exception is thrown
- Non-429 errors are not retried
- Successful response after retry is returned normally

```php
test('socialHttp retries on 429 responses', function () {
    Http::fake([
        'api.example.com/*' => Http::sequence()
            ->push(['error' => 'rate_limit'], 429)
            ->push(['data' => 'success'], 200),
    ]);

    $client = new class { use HasSocialHttpClient; };
    $response = $client->socialHttp()->get('https://api.example.com/test');

    expect($response->status())->toBe(200);
    Http::assertSentCount(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

- [ ] **Step 3: Create HasSocialHttpClient trait**

```php
<?php

declare(strict_types=1);

namespace App\Services\Social\Concerns;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait HasSocialHttpClient
{
    protected function socialHttp(): PendingRequest
    {
        return Http::retry(
            times: 3,
            sleepMilliseconds: 5000,
            when: fn ($exception, $request) => $exception->response?->status() === 429,
            throw: false,
        )->timeout(120);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

- [ ] **Step 5: Integrate into all publishers**

Replace `Http::withToken(...)` calls with `$this->socialHttp()->withToken(...)` in each publisher. The trait adds rate limit retry to all API calls automatically.

For each publisher:
1. Add `use HasSocialHttpClient;` to the class
2. Replace direct `Http::` calls that hit platform APIs with `$this->socialHttp()->`
3. Keep `Http::withOptions(['sink' => ...])` for downloads (those don't need retry)

Publishers to update:
- InstagramPublisher (uses `Http::post`, `Http::get`)
- FacebookPublisher (uses `Http::post`)
- TikTokPublisher (has `getHttpClient()` method — update it to use trait)
- YouTubePublisher (uses Google SDK — skip, SDK has its own retry)
- LinkedInPublisher (has `getHttpClient()` method — update it)
- LinkedInPagePublisher (has `getHttpClient()` method — update it)
- XPublisher (uses `Http::withToken`)
- ThreadsPublisher (uses `Http::post`, `Http::get`)
- PinterestPublisher (uses `Http::withToken`)
- BlueskyPublisher (uses `Http::withToken`)
- MastodonPublisher (uses `Http::withToken`)

- [ ] **Step 6: Run all publisher tests**

```bash
php artisan test --compact --filter="Unit.*Publisher"
```

- [ ] **Step 7: Commit**

```bash
git commit -m "feat: add rate limit retry (429) to all publishers via HasSocialHttpClient trait"
```

---

### Task 2: Token refresh inline during publishing

**Files:**
- Create: `app/Services/Social/TokenRefresher.php`
- Modify: `app/Jobs/PublishToSocialPlatform.php`
- Test: `tests/Unit/Services/Social/TokenRefresherTest.php`
- Test: `tests/Feature/Jobs/PublishToSocialPlatformTest.php` (add inline refresh test)

- [ ] **Step 1: Create TokenRefresher service**

Extract the refresh logic from `ConnectionVerifier::refreshTokenIfNeeded` into a standalone service that all publishers and the job can use:

```php
<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\SocialAccount\Platform;
use App\Exceptions\TokenExpiredException;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TokenRefresher
{
    public function refresh(SocialAccount $account): void
    {
        match ($account->platform) {
            Platform::LinkedIn, Platform::LinkedInPage => $this->refreshLinkedIn($account),
            Platform::X => $this->refreshX($account),
            Platform::YouTube => $this->refreshYouTube($account),
            Platform::TikTok => $this->refreshTikTok($account),
            Platform::Pinterest => $this->refreshPinterest($account),
            Platform::Threads => $this->refreshThreads($account),
            Platform::Instagram => $this->refreshInstagram($account),
            Platform::Bluesky => $this->refreshBluesky($account),
            default => throw new TokenExpiredException('Token refresh not supported for ' . $account->platform->value),
        };

        $account->refresh();
    }

    // ... private methods extracted from ConnectionVerifier
}
```

- [ ] **Step 2: Write test for TokenRefresher**

Test that each platform refresh works (mock HTTP calls).

- [ ] **Step 3: Update PublishToSocialPlatform job with inline retry**

Replace the current try/catch with a retry loop:

```php
$maxAttempts = 2;

for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
    try {
        $publisher = $this->getPublisher();
        $result = $publisher->publish($this->postPlatform);
        $this->postPlatform->markAsPublished(data_get($result, 'id'), data_get($result, 'url'));
        break;
    } catch (TokenExpiredException $e) {
        if ($attempt < $maxAttempts) {
            try {
                app(TokenRefresher::class)->refresh($this->postPlatform->socialAccount);
                continue;
            } catch (\Throwable $refreshError) {
                // Refresh failed — fall through to disconnect
            }
        }

        Log::error('Token expired while publishing', [...]);
        $this->postPlatform->markAsFailed($e->getMessage());
        $this->postPlatform->socialAccount->markAsDisconnected($e->getMessage());
        break;
    } catch (SocialPublishException $e) {
        Log::error('Social publish failed: ' . $e->userMessage);
        $this->postPlatform->markAsFailed($e->userMessage);
        break;
    } catch (\Throwable $e) {
        Log::error('Unexpected publish error', [...]);
        $this->postPlatform->markAsFailed($e->getMessage());
        break;
    }
}
```

- [ ] **Step 4: Write test for inline token refresh**

Test that when publish throws `TokenExpiredException`, the job refreshes the token and retries. On second failure, it disconnects.

- [ ] **Step 5: Update ConnectionVerifier to use TokenRefresher**

Replace duplicated refresh logic in `ConnectionVerifier` with calls to `TokenRefresher`.

- [ ] **Step 6: Remove duplicated refresh methods from publishers**

Each publisher currently has its own `refreshToken()` method. After `TokenRefresher` exists, publishers should delegate to it. However, this is a larger refactor — for now, keep the publisher refresh methods and just add the inline retry in the job.

- [ ] **Step 7: Run tests and commit**

```bash
php artisan test --compact --filter="PublishToSocialPlatform|TokenRefresher"
git commit -m "feat: inline token refresh retry during publishing"
```

---

### Task 3: Per-platform concurrency control via Horizon queues

**Files:**
- Modify: `config/horizon.php`
- Modify: `app/Jobs/PublishToSocialPlatform.php`

- [ ] **Step 1: Add per-platform queues to Horizon config**

In `config/horizon.php`, add supervisor blocks for each platform:

```php
'environments' => [
    'production' => [
        'social-default' => [
            'connection' => 'redis',
            'queue' => [
                'social-instagram',
                'social-facebook',
                'social-tiktok',
                'social-youtube',
                'social-linkedin',
                'social-linkedin-page',
                'social-x',
                'social-threads',
                'social-pinterest',
                'social-bluesky',
                'social-mastodon',
            ],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'timeout' => 630,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 1,
            'nice' => 0,
        ],
    ],
    'local' => [
        'social-default' => [
            'connection' => 'redis',
            'queue' => [
                'social-instagram',
                'social-facebook',
                'social-tiktok',
                'social-youtube',
                'social-linkedin',
                'social-linkedin-page',
                'social-x',
                'social-threads',
                'social-pinterest',
                'social-bluesky',
                'social-mastodon',
            ],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 1,
            'timeout' => 630,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 1,
            'nice' => 0,
        ],
    ],
],
```

- [ ] **Step 2: Update PublishToSocialPlatform to dispatch to platform queue**

```php
public function __construct(public PostPlatform $postPlatform)
{
    $this->onQueue('social-' . $postPlatform->platform->value);
}
```

- [ ] **Step 3: Run tests and commit**

```bash
php artisan test --compact --filter="PublishToSocialPlatform"
git commit -m "feat: per-platform Horizon queues for concurrency control"
```

---

### Task 4: Proactive token refresh

**Files:**
- Create: `app/Console/Commands/RefreshExpiringTokens.php`
- Create: `app/Jobs/RefreshSocialToken.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Commands/RefreshExpiringTokensTest.php`

- [ ] **Step 1: Create RefreshSocialToken job**

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SocialAccount;
use App\Services\Social\TokenRefresher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RefreshSocialToken implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public SocialAccount $account) {}

    public function handle(TokenRefresher $refresher): void
    {
        try {
            $refresher->refresh($this->account);
        } catch (\Throwable $e) {
            Log::warning('Proactive token refresh failed', [
                'account_id' => $this->account->id,
                'platform' => $this->account->platform->value,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

- [ ] **Step 2: Create RefreshExpiringTokens command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SocialAccount\Status;
use App\Jobs\RefreshSocialToken;
use App\Models\SocialAccount;
use Illuminate\Console\Command;

class RefreshExpiringTokens extends Command
{
    protected $signature = 'social:refresh-expiring-tokens';

    protected $description = 'Proactively refresh tokens expiring in the next 2 hours';

    public function handle(): void
    {
        SocialAccount::query()
            ->where('status', Status::Connected)
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', now()->addHours(2))
            ->where('token_expires_at', '>', now())
            ->chunk(50, fn ($accounts) => $accounts->each(
                fn ($account) => RefreshSocialToken::dispatch($account)
            ));
    }
}
```

- [ ] **Step 3: Schedule the command**

In `routes/console.php`:
```php
Schedule::command(RefreshExpiringTokens::class)->hourly();
```

- [ ] **Step 4: Write tests**

Test that the command dispatches jobs for accounts with tokens expiring in 2 hours, and does NOT dispatch for tokens expiring in 5 hours or already expired.

- [ ] **Step 5: Run tests and commit**

```bash
php artisan test --compact --filter="RefreshExpiring"
git commit -m "feat: proactive token refresh for tokens expiring within 2 hours"
```

---

## FUTURE — Plan Only (Not Implementing Now)

### Task 5: Webhooks post-publish

**Scope:** Full webhook system for post lifecycle events.

**Data model:**
- `webhooks` table: id, workspace_id, url, events (json array), secret (encrypted), is_active, created_at, updated_at
- Events: `post.published`, `post.failed`, `post.partially_published`, `account.disconnected`

**Architecture:**
- `Webhook` model with `workspace` relationship
- `SendWebhook` job — dispatched after status change, signs payload with HMAC-SHA256, retries 3x with exponential backoff
- Webhook management CRUD (controller, form requests, Vue components)
- Webhook delivery logs table for debugging

**Integration points:**
- `PublishToSocialPlatform` job — dispatch `SendWebhook` after markAsPublished/markAsFailed
- `SocialAccount::markAsDisconnected` — dispatch `SendWebhook` for account.disconnected

**Estimated effort:** 2-3 days (backend + frontend + tests)

---

### Task 6: Threads / Comments support

**Scope:** Support posting a main post + sequential comments/replies as a thread.

**Data model changes:**
- Add `parent_id` (nullable, self-referencing FK) to `post_platforms`
- Add `delay_seconds` (int, default 0) to `post_platforms`
- Add `thread_position` (int) to `post_platforms`

**Publisher changes:**
- Add `comment(string $postId, string $content, ?array $media): array` method to each publisher that supports it:
  - Instagram: `POST /{media-id}/comments`
  - X/Twitter: `POST /2/tweets` with `reply.in_reply_to_tweet_id`
  - Facebook: `POST /{post-id}/comments`
  - LinkedIn: `POST /rest/socialActions/{post-urn}/comments`
  - Threads: `POST /{user-id}/threads` with `reply_to_id`

**Job changes:**
- `PublishToSocialPlatform` publishes main post first
- Then iterates over child posts (ordered by thread_position)
- Waits `delay_seconds` between each
- Each child calls `publisher->comment()` with the parent's platform_post_id

**Frontend changes:**
- Thread builder UI in post editor
- Drag-to-reorder thread items
- Per-item content and media
- Delay configuration between items

**Estimated effort:** 1-2 weeks (data model + backend + frontend + tests)
