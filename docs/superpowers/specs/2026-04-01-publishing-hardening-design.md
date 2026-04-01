# Publishing Hardening — Best Practices from Postiz

Improvements to the existing publishing flow. No new features — just making what we have more robust.

## 1. Content Sanitization Before Publishing

### Problem

We send raw content to platform APIs. If the user pastes HTML from the editor or has formatting tags, they get sent as-is. Each platform has different rules:
- Instagram, TikTok, Pinterest, Bluesky: plain text only
- LinkedIn: supports bold/italic via Unicode characters
- X: plain text only
- Facebook, Threads: plain text only
- Mastodon: supports some HTML
- YouTube: plain text titles

### Solution

Create a `ContentSanitizer` service that strips/converts content per platform:

```php
class ContentSanitizer
{
    public function sanitize(string $content, Platform $platform): string
    {
        return match ($platform) {
            Platform::LinkedIn, Platform::LinkedInPage => $this->convertToUnicodeBold($this->stripHtml($content)),
            Platform::Mastodon => $this->stripUnsafeHtml($content),
            default => $this->stripHtml($content),
        };
    }

    private function stripHtml(string $content): string
    {
        // Remove HTML tags, decode entities (&amp; → &, &nbsp; → space, etc.)
    }

    private function convertToUnicodeBold(string $content): string
    {
        // Convert <strong>text</strong> to Unicode bold characters (𝗯𝗼𝗹𝗱)
        // Convert <u>text</u> to Unicode underline (t̲e̲x̲t̲)
    }

    private function stripUnsafeHtml(string $content): string
    {
        // Allow only safe tags: <p>, <strong>, <em>, <a>, <br>
        // Strip everything else
    }
}
```

Each publisher calls `$this->sanitizeContent($content, $platform)` before sending to the API.

### Files Changed

- Create: `app/Services/Social/ContentSanitizer.php`
- Modify: All 11 publishers — sanitize content before API call
- Test: `tests/Unit/Services/Social/ContentSanitizerTest.php`

---

## 2. Backend Content Length Validation Before Publishing

### Problem

We validate content length in the frontend only. If the frontend has a bug or someone uses the API directly, content that exceeds platform limits goes through and fails with a cryptic API error.

### Solution

Validate content length in each publisher's `publish()` method before making any API calls:

```php
// In each publisher, at the start of publish():
$maxLength = $postPlatform->platform->maxContentLength();

if ($postPlatform->content && mb_strlen($postPlatform->content) > $maxLength) {
    throw new \Exception("Content exceeds {$postPlatform->platform->label()} limit of {$maxLength} characters.");
}
```

Better: extract to the `HasSocialHttpClient` trait as a shared method:

```php
protected function validateContentLength(PostPlatform $postPlatform): void
{
    $maxLength = $postPlatform->platform->maxContentLength();

    if ($postPlatform->content && mb_strlen($postPlatform->content) > $maxLength) {
        throw new \Exception(
            "Content exceeds {$postPlatform->platform->label()} limit of {$maxLength} characters."
        );
    }
}
```

### Files Changed

- Modify: `app/Services/Social/Concerns/HasSocialHttpClient.php` — add `validateContentLength()`
- Modify: All 11 publishers — call `$this->validateContentLength($postPlatform)` at start of `publish()`
- Test: Add tests for content length validation

---

## 3. Scope Verification Before Publishing

### Problem

We save scopes during OAuth connection but never verify them again. If the user revokes a permission (e.g., removes `instagram_business_content_publish` from the app), we only find out when the post fails with a confusing error.

### Solution

Add a `requiredScopes()` method to the `Platform` enum and verify before publishing:

```php
// In Platform enum:
public function requiredPublishScopes(): array
{
    return match ($this) {
        self::Instagram => ['instagram_business_content_publish'],
        self::Facebook => ['pages_manage_posts'],
        self::TikTok => ['video.publish'],
        self::YouTube => ['https://www.googleapis.com/auth/youtube.upload'],
        self::LinkedIn, self::LinkedInPage => ['w_member_social'],
        self::X => ['tweet.write'],
        self::Threads => ['threads_basic', 'threads_content_publish'],
        self::Pinterest => ['pins:write'],
        self::Bluesky => [],  // no scopes, uses app password
        self::Mastodon => ['write:statuses'],
    };
}
```

Check in `PublishToSocialPlatform` before calling the publisher:

```php
$requiredScopes = $this->postPlatform->platform->requiredPublishScopes();
$accountScopes = $this->postPlatform->socialAccount->scopes ?? [];

$missingScopes = array_diff($requiredScopes, $accountScopes);

if (! empty($missingScopes)) {
    $this->postPlatform->markAsFailed(
        'Missing permissions: ' . implode(', ', $missingScopes) . '. Please reconnect your account.'
    );
    $this->postPlatform->socialAccount->markAsDisconnected('Missing required scopes');
    return;
}
```

### Files Changed

- Modify: `app/Enums/SocialAccount/Platform.php` — add `requiredPublishScopes()`
- Modify: `app/Jobs/PublishToSocialPlatform.php` — add scope check before publish
- Test: Add test for missing scopes scenario

---

## 4. "Refresh Needed" State (separate from "Disconnected")

### Problem

When a token refresh fails, we immediately mark the account as "disconnected". This is too aggressive — the account isn't disconnected, it just needs a new token. The user sees "disconnected" and thinks something is broken, when they just need to re-authenticate.

Postiz has three separate states: `connected`, `refreshNeeded`, `disabled`.

### Solution

Add a `TokenExpired` status to the SocialAccount status enum (we already have it but don't use it consistently):

```php
enum Status: string
{
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case TokenExpired = 'token_expired';  // exists but underused
}
```

When token refresh fails during publishing:
1. First failure → mark as `TokenExpired` (not `Disconnected`)
2. User gets notification: "Your Instagram token expired, please reconnect"
3. Daily `CheckSocialConnections` → if still `TokenExpired`, try refresh again
4. If still failing after daily check → then mark as `Disconnected`

### Files Changed

- Modify: `app/Jobs/PublishToSocialPlatform.php` — use `TokenExpired` instead of `Disconnected` on first failure
- Modify: `app/Jobs/VerifyWorkspaceConnections.php` — handle `TokenExpired` state
- Modify: Frontend — show different UI for `TokenExpired` vs `Disconnected`
- Test: Add tests for state transitions

---

## 5. Error Context Saved in Database

### Problem

When a post fails, we save only `error_message` (the user-facing string). We have no record of what content/media was sent, what the API returned, etc. Debugging requires checking logs.

### Solution

Save structured error context in the existing `meta` JSON column on `post_platforms`:

```php
// In PublishToSocialPlatform when marking as failed:
$this->postPlatform->update([
    'status' => Status::Failed,
    'error_message' => $e->userMessage,
    'meta' => array_merge($this->postPlatform->meta ?? [], [
        'error_context' => [
            'category' => $e->category->value,
            'platform_error_code' => $e->platformErrorCode,
            'failed_at' => now()->toIso8601String(),
            'content_length' => mb_strlen($this->postPlatform->content ?? ''),
            'media_count' => $this->postPlatform->media->count(),
        ],
    ]),
]);
```

No raw API response in the database — that stays in logs only. Just enough context to understand what happened.

### Files Changed

- Modify: `app/Jobs/PublishToSocialPlatform.php` — save error context to meta
- Modify: `app/Models/PostPlatform.php` — add helper `markAsFailedWithContext()`
- Frontend: Show error context in post detail view (optional)

---

## 6. Stuck Post Recovery

### Problem

If a post gets stuck in `publishing` status (job crashed, worker died, etc.), it stays there forever. The user sees "Publishing..." indefinitely.

Postiz has `searchForMissingThreeHoursPosts()` that finds stuck posts and re-dispatches.

### Solution

Create a scheduled command that runs every 30 minutes:

```php
class RecoverStuckPosts extends Command
{
    protected $signature = 'social:recover-stuck-posts';

    public function handle(): void
    {
        // Find posts stuck in "publishing" for more than 1 hour
        Post::query()
            ->where('status', PostStatus::Publishing)
            ->where('updated_at', '<=', now()->subHour())
            ->each(function (Post $post) {
                // Check if any platform is still actively processing
                $activeJobs = $post->postPlatforms()
                    ->where('enabled', true)
                    ->where('status', PostPlatformStatus::Publishing)
                    ->count();

                if ($activeJobs === 0) {
                    // All platforms finished but post status wasn't updated (race condition)
                    $this->recalculatePostStatus($post);
                } else {
                    // Mark stuck platforms as failed
                    $post->postPlatforms()
                        ->where('status', PostPlatformStatus::Publishing)
                        ->where('updated_at', '<=', now()->subHour())
                        ->update([
                            'status' => PostPlatformStatus::Failed,
                            'error_message' => 'Publishing timed out. Please try again.',
                        ]);

                    $this->recalculatePostStatus($post);
                }
            });
    }
}
```

### Files Changed

- Create: `app/Console/Commands/RecoverStuckPosts.php`
- Modify: `routes/console.php` — schedule every 30 minutes
- Test: `tests/Feature/Commands/RecoverStuckPostsTest.php`

---

## Priority

| # | Improvement | Impact | Effort |
|---|---|---|---|
| 1 | Content sanitization | High — prevents HTML in posts | Medium |
| 2 | Backend content length validation | High — prevents cryptic API errors | Low |
| 3 | Scope verification | Medium — early error detection | Low |
| 5 | Error context in database | Medium — easier debugging | Low |
| 6 | Stuck post recovery | High — prevents stuck UI | Low |
| 4 | Refresh needed state | Medium — better UX | Medium |
