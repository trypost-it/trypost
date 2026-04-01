# Social Error Mapping Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace generic exceptions in all social publishers with platform-specific exceptions that give users clear error messages and provide structured context for Nightwatch.

**Architecture:** Abstract base `SocialPublishException` with Laravel's native `context()` method, per-platform subclasses that parse API responses via `fromApiResponse()`, and an `ErrorCategory` enum. Token errors stay as `TokenExpiredException`. The `PublishToSocialPlatform` job catches `SocialPublishException` separately.

**Tech Stack:** Laravel 13, PHP 8.4, Pest 4

**Spec:** `docs/superpowers/specs/2026-03-31-social-error-mapping-design.md`

---

### Task 1: Create ErrorCategory enum and SocialPublishException base class

**Files:**
- Create: `app/Exceptions/Social/ErrorCategory.php`
- Create: `app/Exceptions/Social/SocialPublishException.php`
- Test: `tests/Unit/Exceptions/Social/SocialPublishExceptionTest.php`

- [ ] **Step 1: Create ErrorCategory enum**

Create `app/Exceptions/Social/ErrorCategory.php` with cases: MediaFormat, RateLimit, Permission, ContentPolicy, ServerError, Unknown.

- [ ] **Step 2: Create SocialPublishException base class**

Create `app/Exceptions/Social/SocialPublishException.php` with constructor taking `$userMessage`, `$category`, `$platformErrorCode`, `$rawResponse`. Implement `context()` method. Declare abstract `fromApiResponse()` and `platform()`.

- [ ] **Step 3: Write test for base class context()**

Create test verifying `context()` returns correct array with platform, category, error code, message, and raw response.

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter=SocialPublishException`

- [ ] **Step 5: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Exceptions/Social/ tests/Unit/Exceptions/Social/
git commit -m "feat: add ErrorCategory enum and SocialPublishException base class"
```

---

### Task 2: Create InstagramPublishException

**Files:**
- Create: `app/Exceptions/Social/InstagramPublishException.php`
- Test: `tests/Unit/Exceptions/Social/InstagramPublishExceptionTest.php`

- [ ] **Step 1: Write failing tests**

Test that:
- Subcode 2207026 maps to "Unsupported video format" with MediaFormat category
- Subcode 2207042 maps to RateLimit category
- Subcode 2207050 maps to Permission category
- OAuthException type throws TokenExpiredException
- Unknown subcode falls through to error_user_msg
- Unknown subcode without error_user_msg falls through to error.message

- [ ] **Step 2: Run tests to verify they fail**

- [ ] **Step 3: Implement InstagramPublishException**

Use the code from the spec — match on `error_subcode` (int), handle all 25 Instagram error codes. Check token errors first. Fallback to `error_user_msg` then `error.message`.

- [ ] **Step 4: Run tests to verify they pass**

- [ ] **Step 5: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Exceptions/Social/InstagramPublishException.php tests/Unit/Exceptions/Social/InstagramPublishExceptionTest.php
git commit -m "feat: add InstagramPublishException with 25 error codes"
```

---

### Task 3: Create TikTokPublishException

**Files:**
- Create: `app/Exceptions/Social/TikTokPublishException.php`
- Test: `tests/Unit/Exceptions/Social/TikTokPublishExceptionTest.php`

- [ ] **Step 1: Write failing tests**

Test HTTP errors (access_token_invalid → TokenExpiredException, rate_limit_exceeded → RateLimit, file_format_check_failed → MediaFormat) and fail_reason errors (spam_risk_too_many_posts → RateLimit, video_pull_failed → ServerError).

- [ ] **Step 2: Run tests to verify they fail**

- [ ] **Step 3: Implement TikTokPublishException**

Two parsing paths: HTTP response errors match on `error.code` string, publish status errors match on `fail_reason` string. Add static method `fromFailReason(string $failReason, ?string $rawResponse)` for publish status failures.

- [ ] **Step 4: Run tests to verify they pass**

- [ ] **Step 5: Run Pint and commit**

```bash
git commit -m "feat: add TikTokPublishException with HTTP and fail_reason errors"
```

---

### Task 4: Create YouTubePublishException

**Files:**
- Create: `app/Exceptions/Social/YouTubePublishException.php`
- Test: `tests/Unit/Exceptions/Social/YouTubePublishExceptionTest.php`

- [ ] **Step 1: Write failing tests**

Test: invalidTitle → ContentPolicy, uploadLimitExceeded → RateLimit, forbidden → Permission, HTTP 401 → TokenExpiredException.

- [ ] **Step 2: Run tests to verify they fail**

- [ ] **Step 3: Implement YouTubePublishException**

Parse `Google\Service\Exception` — match on `getErrors()[0]['reason']` string. Handle HTTP 401 as TokenExpiredException.

- [ ] **Step 4: Run tests to verify they pass**

- [ ] **Step 5: Run Pint and commit**

```bash
git commit -m "feat: add YouTubePublishException with 15 error reasons"
```

---

### Task 5: Create FacebookPublishException

**Files:**
- Create: `app/Exceptions/Social/FacebookPublishException.php`
- Test: `tests/Unit/Exceptions/Social/FacebookPublishExceptionTest.php`

- [ ] **Step 1: Write failing tests**

Test: code 1363031 → MediaFormat, code 190 → TokenExpiredException, code 4 → RateLimit, code 1363042 → Permission.

- [ ] **Step 2: Implement and test**

Match on `error.code` (int). Token errors checked first by OAuthException type or code 190 + subcodes 458-467. Map all 30 error codes from spec.

- [ ] **Step 3: Run Pint and commit**

```bash
git commit -m "feat: add FacebookPublishException with 30 error codes"
```

---

### Task 6: Create remaining 6 platform exceptions

**Files:**
- Create: `app/Exceptions/Social/LinkedInPublishException.php`
- Create: `app/Exceptions/Social/XPublishException.php`
- Create: `app/Exceptions/Social/ThreadsPublishException.php`
- Create: `app/Exceptions/Social/PinterestPublishException.php`
- Create: `app/Exceptions/Social/BlueskyPublishException.php`
- Create: `app/Exceptions/Social/MastodonPublishException.php`
- Test: `tests/Unit/Exceptions/Social/` (one test file per exception)

- [ ] **Step 1: Create LinkedInPublishException with tests**

Match on HTTP status + body text. 5 error mappings from spec.

- [ ] **Step 2: Create XPublishException with tests**

Match on Problem `type` suffix + HTTP status. 10 error mappings from spec.

- [ ] **Step 3: Create ThreadsPublishException with tests**

Same Graph API format as Instagram. Match on error.type + error.code.

- [ ] **Step 4: Create PinterestPublishException with tests**

Match on HTTP status + processing status. 6 error mappings.

- [ ] **Step 5: Create BlueskyPublishException with tests**

Match on AT Protocol error strings. 6 error mappings.

- [ ] **Step 6: Create MastodonPublishException with tests**

Match on HTTP status + error message text. 7 error mappings.

- [ ] **Step 7: Run full test suite and commit**

```bash
php artisan test --compact
git commit -m "feat: add error mapping for LinkedIn, X, Threads, Pinterest, Bluesky, Mastodon"
```

---

### Task 7: Update PublishToSocialPlatform job

**Files:**
- Modify: `app/Jobs/PublishToSocialPlatform.php`
- Test: `tests/Feature/Jobs/PublishToSocialPlatformTest.php`

- [ ] **Step 1: Write failing test**

Test that when a publisher throws `SocialPublishException`, the job saves `$e->userMessage` to `error_message` (not the raw API response).

- [ ] **Step 2: Add SocialPublishException catch block**

Between the `TokenExpiredException` catch and the `\Throwable` catch, add:

```php
} catch (SocialPublishException $e) {
    Log::error('Social publish failed: ' . $e->userMessage);
    $this->postPlatform->markAsFailed($e->userMessage);
}
```

- [ ] **Step 3: Run tests to verify pass**

- [ ] **Step 4: Run Pint and commit**

```bash
git commit -m "feat: PublishToSocialPlatform catches SocialPublishException"
```

---

### Task 8: Replace handleApiError in InstagramPublisher

**Files:**
- Modify: `app/Services/Social/InstagramPublisher.php`
- Test: `tests/Feature/Jobs/PublishToSocialPlatformTest.php` (existing Instagram tests)

- [ ] **Step 1: Replace handleApiError method**

Replace the existing `handleApiError` with:

```php
private function handleApiError(Response $response): never
{
    throw InstagramPublishException::fromApiResponse($response);
}
```

Remove the `TOKEN_ERROR_CODES` and `TOKEN_ERROR_SUBCODES` constants (now handled inside the exception).

- [ ] **Step 2: Run tests**

Run: `php artisan test --compact --filter=PublishToSocialPlatform`

- [ ] **Step 3: Commit**

```bash
git commit -m "refactor: InstagramPublisher uses InstagramPublishException"
```

---

### Task 9: Replace handleApiError in TikTok, YouTube, Facebook publishers

**Files:**
- Modify: `app/Services/Social/TikTokPublisher.php`
- Modify: `app/Services/Social/YouTubePublisher.php`
- Modify: `app/Services/Social/FacebookPublisher.php`

- [ ] **Step 1: Update TikTokPublisher**

Replace `handleApiError` with `TikTokPublishException::fromApiResponse()`. Also update the `waitForPublishStatus` method to use `TikTokPublishException::fromFailReason()` when status is FAILED.

- [ ] **Step 2: Update YouTubePublisher**

Replace `handleGoogleError` with `YouTubePublishException::fromGoogleException()`. This takes a `Google\Service\Exception` instead of an HTTP response.

- [ ] **Step 3: Update FacebookPublisher**

Replace `handleApiError` with `FacebookPublishException::fromApiResponse()`. Remove TOKEN_ERROR constants.

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact`

- [ ] **Step 5: Commit**

```bash
git commit -m "refactor: TikTok, YouTube, Facebook publishers use platform exceptions"
```

---

### Task 10: Replace handleApiError in remaining 6 publishers

**Files:**
- Modify: `app/Services/Social/LinkedInPublisher.php`
- Modify: `app/Services/Social/LinkedInPagePublisher.php`
- Modify: `app/Services/Social/XPublisher.php`
- Modify: `app/Services/Social/ThreadsPublisher.php`
- Modify: `app/Services/Social/PinterestPublisher.php`
- Modify: `app/Services/Social/BlueskyPublisher.php`
- Modify: `app/Services/Social/MastodonPublisher.php`

- [ ] **Step 1: Update LinkedInPublisher and LinkedInPagePublisher**

Both share the same error format. Replace `handleApiError` with `LinkedInPublishException::fromApiResponse()`.

- [ ] **Step 2: Update XPublisher**

Replace `handleApiError` with `XPublishException::fromApiResponse()`.

- [ ] **Step 3: Update ThreadsPublisher**

Replace `handleApiError` with `ThreadsPublishException::fromApiResponse()`. Remove TOKEN constants.

- [ ] **Step 4: Update PinterestPublisher**

Replace `handleApiError` with `PinterestPublishException::fromApiResponse()`.

- [ ] **Step 5: Update BlueskyPublisher**

Bluesky error handling is scattered (inline checks). Consolidate into `BlueskyPublishException::fromApiResponse()`.

- [ ] **Step 6: Update MastodonPublisher**

Replace `handleApiError` with `MastodonPublishException::fromApiResponse()`.

- [ ] **Step 7: Run full test suite**

Run: `php artisan test --compact`

- [ ] **Step 8: Commit**

```bash
git commit -m "refactor: all publishers use platform-specific exceptions"
```

---

### Task 11: Final verification

- [ ] **Step 1: Run full test suite**

```bash
php artisan test --compact
```

All 917+ tests must pass.

- [ ] **Step 2: Verify no remaining generic exceptions in publishers**

```bash
grep -rn "throw new \\\\Exception" app/Services/Social/ | grep -v "TokenExpiredException\|SocialPublishException"
```

Should return only legitimate non-API exceptions (e.g., "requires media", "only supports video").

- [ ] **Step 3: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Final commit and push**

```bash
git push
```
