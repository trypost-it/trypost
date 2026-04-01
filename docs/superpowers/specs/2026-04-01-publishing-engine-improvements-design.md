# Publishing Engine Improvements

Based on comparative analysis of Postiz's publishing engine vs ours.

## 1. Rate Limit Retry (429 handling)

### Problem

When a platform API returns 429 (Too Many Requests), our publishers throw an exception and the post fails. The user has to manually retry. Postiz retries automatically with a 5-second delay, up to 3 times.

### Solution

Add a `retry()` middleware to all HTTP calls that hit social platform APIs. Laravel's HTTP client supports `retry()` natively.

```php
// Before:
$response = Http::withToken($token)->post($url, $data);

// After:
$response = Http::withToken($token)
    ->retry(3, 5000, fn ($e, $request) => $e->response?->status() === 429)
    ->post($url, $data);
```

### Implementation

Create a trait `HasSocialHttpClient` that all publishers use:

```php
trait HasSocialHttpClient
{
    protected function socialHttp(): PendingRequest
    {
        return Http::retry(
            times: 3,
            sleepMilliseconds: 5000,
            when: fn ($exception, $request) => $exception->response?->status() === 429,
            throw: false,
        );
    }
}
```

Each publisher replaces `Http::withToken(...)` calls with `$this->socialHttp()->withToken(...)`.

### Files Changed

- Create: `app/Services/Social/Concerns/HasSocialHttpClient.php`
- Modify: All 11 publishers to use the trait

---

## 2. Token Refresh Inline During Publishing

### Problem

We refresh tokens **before** publishing, but if the token expires **during** a long upload (e.g., 244MB YouTube video), the publish fails. Postiz retries up to 5 times with inline token refresh between attempts.

### Solution

Wrap the publish call in the `PublishToSocialPlatform` job with a retry loop that catches `TokenExpiredException`, refreshes the token, and retries:

```php
// In PublishToSocialPlatform::handle()
$maxAttempts = 2; // 1 retry after token refresh

for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
    try {
        $result = $publisher->publish($this->postPlatform);
        $this->postPlatform->markAsPublished(...);
        break;
    } catch (TokenExpiredException $e) {
        if ($attempt < $maxAttempts) {
            $this->refreshTokenAndRetry($e);
            continue;
        }
        // Final attempt failed — disconnect account
        $this->postPlatform->markAsFailed($e->getMessage());
        $this->postPlatform->socialAccount->markAsDisconnected($e->getMessage());
    }
}
```

The `refreshTokenAndRetry` method calls the platform-specific refresh (same logic as `ConnectionVerifier::refreshTokenIfNeeded`).

### Files Changed

- Modify: `app/Jobs/PublishToSocialPlatform.php`
- Extract: Token refresh logic from `ConnectionVerifier` into a reusable `TokenRefresher` service

---

## 3. Concurrency Control Per Platform

### Problem

If 50 posts are scheduled for the same time, all 50 jobs hit Instagram's API simultaneously, causing rate limits and failures. Postiz uses per-platform task queues with `maxConcurrentJob`.

### Solution

Use Horizon's queue configuration to create per-platform queues with max processes:

```php
// config/horizon.php
'environments' => [
    'production' => [
        'social-instagram' => [
            'connection' => 'redis',
            'queue' => ['social-instagram'],
            'maxProcesses' => 2,
            'timeout' => 630,
        ],
        'social-tiktok' => [
            'connection' => 'redis',
            'queue' => ['social-tiktok'],
            'maxProcesses' => 2,
            'timeout' => 630,
        ],
        // ... per platform
    ],
],
```

The `PublishToSocialPlatform` job dispatches to the platform-specific queue:

```php
public function __construct(public PostPlatform $postPlatform)
{
    $this->onQueue('social-' . $postPlatform->platform->value);
}
```

### Platform Concurrency Limits (from Postiz)

| Platform | Max Concurrent | Our Queue maxProcesses |
|---|---|---|
| Instagram | 400 | 3 |
| TikTok | 300 | 2 |
| YouTube | 200 | 1 |
| Facebook | default | 3 |
| LinkedIn | default | 2 |
| X/Twitter | default | 2 |
| Threads | default | 2 |
| Pinterest | default | 2 |
| Bluesky | default | 2 |
| Mastodon | default | 2 |

### Files Changed

- Modify: `config/horizon.php` — add per-platform queues
- Modify: `app/Jobs/PublishToSocialPlatform.php` — dispatch to platform queue
- Modify: `app/Jobs/PublishPost.php` — pass platform info when dispatching

---

## 4. Webhooks Post-Publish

### Problem

Users building integrations (Zapier, Make, custom CRM) can't programmatically know when a post is published. Postiz fires webhooks after each successful publish.

### Solution

Add a `Webhook` model and fire webhooks after post status changes. This is a larger feature that deserves its own spec.

### High-Level Design

- `webhooks` table: `id, workspace_id, url, events (json), secret, is_active`
- Events: `post.published`, `post.failed`, `account.disconnected`
- Fire webhook in `PublishToSocialPlatform` job after status update
- Sign payload with HMAC-SHA256 using the webhook secret
- Async dispatch via a `SendWebhook` job
- Retry 3x with exponential backoff

### Files Changed

- Create: Migration, Model, Controller, FormRequest for Webhooks CRUD
- Create: `app/Jobs/SendWebhook.php`
- Modify: `app/Jobs/PublishToSocialPlatform.php` — dispatch webhook after publish
- Create: Frontend components for webhook management

---

## 5. Threads / Comments Support

### Problem

Postiz supports posting a main post + sequential comments (Twitter threads, Instagram first comment). We only post single posts.

### Solution

This requires significant data model changes:

- A `Post` can have ordered child `Post` records (thread items)
- The publisher publishes the first post, then iterates over children posting each as a reply/comment
- Each platform's comment API is different (Twitter reply_to, Instagram comment endpoint, etc.)

### High-Level Design

- Add `parent_post_platform_id` to `post_platforms` table
- Add `delay_seconds` column for delayed comments
- Extend each publisher with a `comment()` method (like Postiz)
- The job publishes main post → waits for delay → publishes each comment

This is the largest feature. Deserves its own dedicated spec + plan.

### Files Changed

- Migration: Add columns to `post_platforms`
- Modify: All publishers to add `comment()` method
- Modify: Frontend to support thread/comment creation UI
- Modify: `PublishToSocialPlatform` job to handle sequential publishing

---

## 6. Proactive Token Refresh

### Problem

Currently we only refresh tokens reactively (when publishing) and via daily `CheckSocialConnections`. Postiz runs a dedicated workflow per integration that sleeps until token expiry and proactively refreshes.

### Solution

Create a scheduled command that runs every hour and refreshes tokens expiring in the next 2 hours:

```php
// app/Console/Commands/RefreshExpiringTokens.php
SocialAccount::query()
    ->where('status', Status::Connected)
    ->whereNotNull('token_expires_at')
    ->where('token_expires_at', '<=', now()->addHours(2))
    ->where('token_expires_at', '>', now())
    ->chunk(50, function ($accounts) {
        foreach ($accounts as $account) {
            RefreshSocialToken::dispatch($account);
        }
    });
```

### Files Changed

- Create: `app/Console/Commands/RefreshExpiringTokens.php`
- Create: `app/Jobs/RefreshSocialToken.php`
- Modify: `routes/console.php` — schedule hourly

---

## Priority Order

| # | Feature | Impact | Effort | When |
|---|---|---|---|---|
| 1 | Rate limit retry (429) | High | Low | This sprint |
| 2 | Token refresh inline | High | Medium | This sprint |
| 3 | Concurrency control | Medium | Medium | This sprint |
| 6 | Proactive token refresh | Medium | Low | This sprint |
| 4 | Webhooks | Medium | High | Next sprint |
| 5 | Threads/Comments | High | Very High | Future |
