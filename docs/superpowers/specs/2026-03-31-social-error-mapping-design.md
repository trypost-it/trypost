# Social Platform Error Mapping

## Problem

All publishers throw generic `\Exception` or `TokenExpiredException` with raw API error messages. Users see cryptic strings like `"Instagram API error: Only photo or video can be accepted as media type."` instead of actionable messages. Debugging requires reading raw logs.

## Solution

Create per-platform exception classes inside `app/Exceptions/Social/` that parse API responses and return clear user-facing messages, a categorized error type, and structured context for Nightwatch via Laravel's native `context()` method.

## Architecture

### Directory Structure

```
app/Exceptions/
  TokenExpiredException.php              (existing, unchanged)
  Social/
    SocialPublishException.php           (abstract base)
    ErrorCategory.php                    (enum)
    InstagramPublishException.php
    TikTokPublishException.php
    YouTubePublishException.php
    FacebookPublishException.php
    LinkedInPublishException.php
    XPublishException.php
    ThreadsPublishException.php
    PinterestPublishException.php
    BlueskyPublishException.php
    MastodonPublishException.php
```

### ErrorCategory Enum

```php
<?php

declare(strict_types=1);

namespace App\Exceptions\Social;

enum ErrorCategory: string
{
    case MediaFormat = 'media_format';
    case RateLimit = 'rate_limit';
    case Permission = 'permission';
    case ContentPolicy = 'content_policy';
    case ServerError = 'server_error';
    case Unknown = 'unknown';
}
```

### SocialPublishException (Base Class)

Uses Laravel's native `context()` method for structured logging. The `report()` method is not overridden — Laravel's default logging handles it, and the `context()` data is automatically included in every log entry and Nightwatch.

```php
<?php

declare(strict_types=1);

namespace App\Exceptions\Social;

use Exception;

abstract class SocialPublishException extends Exception
{
    public function __construct(
        public readonly string $userMessage,
        public readonly ErrorCategory $category,
        public readonly ?string $platformErrorCode = null,
        public readonly ?string $rawResponse = null,
    ) {
        parent::__construct($userMessage);
    }

    /**
     * Laravel automatically includes this context in all log entries.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'platform' => static::platform(),
            'category' => $this->category->value,
            'platform_error_code' => $this->platformErrorCode,
            'user_message' => $this->userMessage,
            'raw_response' => $this->rawResponse,
        ];
    }

    /**
     * Parse an API response and return a platform-specific exception.
     */
    abstract public static function fromApiResponse(mixed $response): static;

    /**
     * Platform identifier for logging.
     */
    abstract protected static function platform(): string;
}
```

### Per-Platform Exception (Example: Instagram)

`fromApiResponse` receives the Laravel HTTP response, checks for token errors first, then matches on `error_subcode` (the reliable identifier per Instagram's official error docs). When the API includes `error_user_msg`, we prefer that over our own message since it's localized by Meta.

Reference: https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/reference/error-codes/

```php
<?php

declare(strict_types=1);

namespace App\Exceptions\Social;

use App\Exceptions\TokenExpiredException;

class InstagramPublishException extends SocialPublishException
{
    protected static function platform(): string
    {
        return 'instagram';
    }

    public static function fromApiResponse(mixed $response): static
    {
        $json = $response->json() ?? [];
        $error = data_get($json, 'error', []);
        $errorCode = data_get($error, 'code');
        $errorSubcode = data_get($error, 'error_subcode');
        $errorType = data_get($error, 'type');
        $errorUserMsg = data_get($error, 'error_user_msg');

        // Token errors throw TokenExpiredException
        if ($errorType === 'OAuthException' || $errorCode === 190) {
            throw new TokenExpiredException(
                data_get($error, 'message', 'Instagram token expired'),
                (string) $errorCode,
            );
        }

        // Match on error_subcode (official Instagram error identifier)
        [$message, $category] = match ($errorSubcode) {
            // Media format errors
            2207026 => ['Unsupported video format. Please upload MP4 or MOV.', ErrorCategory::MediaFormat],
            2207005 => ['Unsupported image format.', ErrorCategory::MediaFormat],
            2207004 => ['Image is too large (max 8MB).', ErrorCategory::MediaFormat],
            2207009 => ['Aspect ratio not supported (must be between 4:5 and 1.91:1).', ErrorCategory::MediaFormat],
            2207057 => ['Thumbnail offset is outside the video duration.', ErrorCategory::MediaFormat],
            2207023 => ['Unknown media type.', ErrorCategory::MediaFormat],

            // Upload/processing errors
            2207003 => ['Media download timed out. Please try again.', ErrorCategory::ServerError],
            2207020 => ['Media has expired. Please upload again.', ErrorCategory::ServerError],
            2207032 => ['Failed to create media. Please try again.', ErrorCategory::ServerError],
            2207053 => ['Unknown upload error. Please try again.', ErrorCategory::ServerError],
            2207052 => ['Could not fetch media from URL. Please try again.', ErrorCategory::ServerError],
            2207006 => ['Media not found. Please upload again.', ErrorCategory::ServerError],
            2207008 => ['Media container expired. Please try again in a few minutes.', ErrorCategory::ServerError],
            2207027 => ['Media is not ready for publishing. Please wait and try again.', ErrorCategory::ServerError],
            2207001 => ['Instagram server error. Please try again.', ErrorCategory::ServerError],

            // Content validation
            2207010 => ['Caption is too long (max 2,200 characters, 30 hashtags, 20 @mentions).', ErrorCategory::ContentPolicy],
            2207028 => ['Carousel needs between 2 and 10 photos/videos.', ErrorCategory::ContentPolicy],
            2207051 => ['Instagram restricted this action to protect the community.', ErrorCategory::ContentPolicy],

            // Product tagging
            2207035 => ['Product tag positions are not supported for videos.', ErrorCategory::ContentPolicy],
            2207036 => ['Product tag positions are required for photos.', ErrorCategory::ContentPolicy],
            2207037 => ['Invalid product tag. The product may be deleted or not permitted.', ErrorCategory::ContentPolicy],
            2207040 => ['Too many tags (max 20).', ErrorCategory::ContentPolicy],

            // Rate limits
            2207042 => ['Daily publishing limit reached. Please try again tomorrow.', ErrorCategory::RateLimit],

            // Permissions
            2207050 => ['Instagram account is restricted or inactive. Please check the Instagram app.', ErrorCategory::Permission],
            2207081 => ["This account doesn't support Trial Reels.", ErrorCategory::Permission],

            // Fall through — use Instagram's own error_user_msg if available
            default => [null, ErrorCategory::Unknown],
        };

        // Prefer Instagram's own user-facing message when we don't have a mapping
        $message ??= $errorUserMsg ?? data_get($error, 'message', 'Instagram publishing failed');

        return new static(
            $message,
            $category,
            $errorSubcode ? (string) $errorSubcode : (string) $errorCode,
            $response->body(),
        );
    }
}
```

### Error Maps Per Platform

Sources: Official API documentation + Postiz error mappings.

---

#### Instagram (25 errors)

Source: https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/reference/error-codes/

Match on `error_subcode` (int). Use `error_user_msg` as fallback when available.

| Subcode | Message | Category |
|---|---|---|
| 2207026 | Unsupported video format. Please upload MP4 or MOV. | MediaFormat |
| 2207005 | Unsupported image format. | MediaFormat |
| 2207004 | Image is too large (max 8MB). | MediaFormat |
| 2207009 | Aspect ratio not supported (must be between 4:5 and 1.91:1). | MediaFormat |
| 2207057 | Thumbnail offset is outside the video duration. | MediaFormat |
| 2207023 | Unknown media type. | MediaFormat |
| 2207003 | Media download timed out. Please try again. | ServerError |
| 2207020 | Media has expired. Please upload again. | ServerError |
| 2207032 | Failed to create media. Please try again. | ServerError |
| 2207053 | Unknown upload error. Please try again. | ServerError |
| 2207052 | Could not fetch media from URL. Please try again. | ServerError |
| 2207006 | Media not found. Please upload again. | ServerError |
| 2207008 | Media container expired. Please try again in a few minutes. | ServerError |
| 2207027 | Media is not ready for publishing. Please wait and try again. | ServerError |
| 2207001 | Instagram server error. Please try again. | ServerError |
| 2207010 | Caption is too long (max 2,200 characters, 30 hashtags, 20 @mentions). | ContentPolicy |
| 2207028 | Carousel needs between 2 and 10 photos/videos. | ContentPolicy |
| 2207051 | Instagram restricted this action to protect the community. | ContentPolicy |
| 2207035 | Product tag positions are not supported for videos. | ContentPolicy |
| 2207036 | Product tag positions are required for photos. | ContentPolicy |
| 2207037 | Invalid product tag. The product may be deleted or not permitted. | ContentPolicy |
| 2207040 | Too many tags (max 20). | ContentPolicy |
| 2207042 | Daily publishing limit reached. Please try again tomorrow. | RateLimit |
| 2207050 | Instagram account is restricted or inactive. Please check the Instagram app. | Permission |
| 2207081 | This account doesn't support Trial Reels. | Permission |

Token errors: `OAuthException` type or code `190` → `TokenExpiredException`.

---

#### TikTok (20 errors)

Sources: https://developers.tiktok.com/doc/content-posting-api-reference-get-video-status/ and https://developers.tiktok.com/doc/tiktok-api-v2-error-handling

Two types of errors: HTTP response errors (match on `error` string) and publish status fail reasons (match on `fail_reason` string).

**HTTP errors:**

| Error Code | Message | Category |
|---|---|---|
| `access_token_invalid` | Access token is invalid or expired. Please reconnect. | TokenExpiredException |
| `scope_not_authorized` | Missing required permissions. Please reconnect with all scopes. | Permission |
| `scope_permission_missed` | Additional permissions required. Please reconnect. | Permission |
| `rate_limit_exceeded` | TikTok rate limit exceeded. Please try again later. | RateLimit |
| `invalid_file_upload` | File does not meet API specifications. | MediaFormat |
| `invalid_params` | Invalid request parameters. | MediaFormat |
| `internal_error` | TikTok server error. Please try again later. | ServerError |

**Publish fail reasons:**

| Fail Reason | Message | Category |
|---|---|---|
| `file_format_check_failed` | Unsupported media format. | MediaFormat |
| `duration_check_failed` | Video duration is not within allowed limits. | MediaFormat |
| `frame_rate_check_failed` | Video frame rate is not supported. | MediaFormat |
| `picture_size_check_failed` | Image dimensions exceed limits. | MediaFormat |
| `video_pull_failed` | Failed to download video from URL. | ServerError |
| `photo_pull_failed` | Failed to download photo from URL. | ServerError |
| `publish_cancelled` | Publishing was cancelled. | ContentPolicy |
| `auth_removed` | App access was revoked during processing. | Permission |
| `spam_risk_too_many_posts` | Daily posting limit reached. Try again tomorrow. | RateLimit |
| `spam_risk_user_banned_from_posting` | Account is banned from posting. | ContentPolicy |
| `spam_risk_text` | TikTok detected spam in the description. | ContentPolicy |
| `spam_risk` | Publishing request flagged as high-risk. | ContentPolicy |
| `internal` | TikTok server error. Please try again. | ServerError |

Additional HTTP errors from Postiz:
- `reached_active_user_cap` → RateLimit: Daily active user quota reached.
- `unaudited_client_can_only_post_to_private_accounts` → Permission: App not approved for public posting.
- `url_ownership_unverified` → Permission: Domain ownership not verified.
- `privacy_level_option_mismatch` → Permission: Privacy level not available for this account.
- `app_version_check_failed` → Permission: TikTok app update required.

---

#### YouTube (15 errors)

Source: https://developers.google.com/youtube/v3/docs/videos/insert

Match on `reason` field in `Google\Service\Exception::getErrors()[0]['reason']`.

| Reason | Message | Category |
|---|---|---|
| `invalidTitle` | Video title is invalid or empty. | ContentPolicy |
| `invalidDescription` | Video description is invalid. | ContentPolicy |
| `invalidTags` | Video tags are invalid. | ContentPolicy |
| `invalidCategoryId` | Video category is invalid. | ContentPolicy |
| `invalidVideoMetadata` | Video metadata is invalid. Title and category are required. | ContentPolicy |
| `invalidPublishAt` | Scheduled publishing time is invalid. | ContentPolicy |
| `invalidFilename` | Video filename is invalid. | MediaFormat |
| `invalidRecordingDetails` | Recording details are invalid. | ContentPolicy |
| `invalidVideoGameRating` | Video game rating is invalid. | ContentPolicy |
| `mediaBodyRequired` | Video file is missing from the request. | MediaFormat |
| `uploadLimitExceeded` | Daily upload limit reached. Try again tomorrow. | RateLimit |
| `forbidden` | You don't have permission to upload to this channel. | Permission |
| `forbiddenLicenseSetting` | Invalid video license setting. | Permission |
| `forbiddenPrivacySetting` | Invalid video privacy setting. | Permission |
| `failedPrecondition` | Thumbnail too large or account not verified. | MediaFormat |

Token errors: HTTP 401, `Unauthorized`, `UNAUTHENTICATED`, `invalid_grant` → `TokenExpiredException`.

---

#### Facebook (30 errors)

Sources: https://developers.facebook.com/docs/video-api/reference/error-codes/ and https://developers.facebook.com/docs/graph-api/guides/error-handling/

Match on `error.code` (int). Token errors checked first by `error.type === 'OAuthException'` or code `190`.

**Token errors → TokenExpiredException:**
- Code `190` — Token expired
- Subcode `458` — App not installed
- Subcode `459` — User checkpointed
- Subcode `460` — Password changed
- Subcode `463` — Session expired
- Subcode `464` — Unconfirmed user
- Subcode `467` — Invalid token

**Video upload errors (Session init):**

| Code | Message | Category |
|---|---|---|
| 6000 | Problem with file. Try with another file. | MediaFormat |
| 1363042 | No permission to upload video here. | Permission |
| 1363023 | Video exceeds 2GB maximum size. | MediaFormat |
| 1363022 | Video below 1KB minimum size. | MediaFormat |

**Video upload errors (Upload phase):**

| Code | Message | Category |
|---|---|---|
| 1363030 | Upload timed out. Please try again. | ServerError |
| 1363019 | Problem uploading video. Please try again. | ServerError |
| 1363031 | Unsupported file format. | MediaFormat |
| 1363032 | File is not a valid video. | MediaFormat |
| 1363024 | Unsupported video format. | MediaFormat |
| 1363025 | Video is too short (minimum 1 second). | MediaFormat |
| 1363026 | Video is too long (maximum 40 minutes). | MediaFormat |
| 1363033 | Upload interrupted. Please try again. | ServerError |
| 1363037 | Invalid upload offset. | ServerError |
| 1363020 | No video file selected. | MediaFormat |
| 1363045 | Upload size mismatch. | ServerError |
| 1363041 | Upload session expired. Please try again. | ServerError |
| 1363021 | Problem during video upload. Please try again. | ServerError |
| 1363005 | No permission to edit this video. | Permission |

**Reel/Story specific:**

| Code | Message | Category |
|---|---|---|
| 1363047 | Reel encoding issue. Please try a different video. | MediaFormat |
| 1609008 | Video format not supported for Reels. | MediaFormat |
| 1609010 | Reel encoding requirements not met. | MediaFormat |
| 1366046 | Reels require a video. | ContentPolicy |
| 2061006 | Video is too short for this format. | MediaFormat |

**General:**

| Code | Message | Category |
|---|---|---|
| 1390008 | Caption is too long. | ContentPolicy |
| 1346003 | Thumbnail is incompatible. | ContentPolicy |
| 1349125 | Rate limit exceeded. Try again later. | RateLimit |
| 4 | Too many API calls. Please try again later. | RateLimit |
| 17 | User call limit reached. | RateLimit |
| 506 | Duplicate post detected. Please modify content. | ContentPolicy |

---

#### X/Twitter (10 errors)

Source: https://docs.x.com/x-api/fundamentals/response-codes-and-errors

Match on Problem `type` suffix and HTTP status code. X uses RFC 7807 Problem Details.

| Error Type / Status | Message | Category |
|---|---|---|
| `unsupported-authentication` / 401 | Authentication method not supported. Please reconnect. | TokenExpiredException |
| HTTP 401 | Access token is invalid or expired. | TokenExpiredException |
| `usage-capped` / 429 | Usage limit exceeded. Please try again later. | RateLimit |
| `rate-limit-exceeded` / 429 | Rate limit exceeded. Please try again later. | RateLimit |
| `invalid-request` / 400 | Invalid request. Check your post content. | ContentPolicy |
| `client-forbidden` / 403 | App not enrolled or lacks required access. | Permission |
| `not-authorized-for-resource` / 403 | Not authorized for this resource. | Permission |
| `resource-not-found` / 404 | Resource not found. | ContentPolicy |
| `The Tweet contains an invalid URL` | Post contains an invalid URL. | ContentPolicy |
| `video longer than 2 minutes` | Video exceeds the 2-minute limit for this account. | MediaFormat |
| HTTP 500/502/503/504 | X server error. Please try again later. | ServerError |

---

#### LinkedIn (5 errors)

Source: https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/posts-api

LinkedIn uses generic HTTP status codes. Match on HTTP status and response body text.

| Status / Text | Message | Category |
|---|---|---|
| HTTP 401 | LinkedIn access token expired. Please reconnect. | TokenExpiredException |
| HTTP 403 | Not authorized to post to this account. | Permission |
| HTTP 422 | Invalid post data. Please check your content. | ContentPolicy |
| `Unable to obtain activity` | LinkedIn server error. Please try again. | ServerError |
| `resource is forbidden` | Access to this resource is forbidden. | Permission |

---

#### Threads (8 errors)

Source: Threads uses the same Graph API error format as Instagram.

Match on `error.type` and `error.code`. Token errors: `OAuthException` or code `190`.

| Code / Text | Message | Category |
|---|---|---|
| `OAuthException` / 190 | Threads token expired. Please reconnect. | TokenExpiredException |
| HTTP 400 + `text can't be blank` | Post text is required. | ContentPolicy |
| HTTP 400 + media processing error | Media processing failed. Please try again. | ServerError |
| HTTP 429 | Rate limit exceeded. Please try again later. | RateLimit |
| HTTP 500 | Threads server error. Please try again. | ServerError |

---

#### Pinterest (6 errors)

Source: https://developers.pinterest.com/docs/api/v5/

Match on HTTP status code and processing status.

| Status / Text | Message | Category |
|---|---|---|
| HTTP 401 | Pinterest token expired. Please reconnect. | TokenExpiredException |
| HTTP 403 | Not authorized to create pins on this board. | Permission |
| HTTP 429 | Rate limit exceeded. Please try again later. | RateLimit |
| Processing status `failed` | Media processing failed. Please try a different file. | MediaFormat |
| HTTP 400 + board error | Invalid board. Please select a valid board. | ContentPolicy |
| HTTP 500 | Pinterest server error. Please try again. | ServerError |

---

#### Bluesky (6 errors)

Source: https://docs.bsky.app/docs/advanced-guides/posts

Match on AT Protocol error strings and HTTP status.

| Error / Status | Message | Category |
|---|---|---|
| `ExpiredToken` | Bluesky session expired. Please reconnect. | TokenExpiredException |
| `InvalidToken` | Bluesky token is invalid. Please reconnect. | TokenExpiredException |
| Blob size > 1MB | Image exceeds Bluesky's 1MB limit. | MediaFormat |
| HTTP 400 + `InvalidRequest` | Invalid post data. | ContentPolicy |
| HTTP 429 | Rate limit exceeded. Please try again later. | RateLimit |
| HTTP 500/502 | Bluesky server error. Please try again. | ServerError |

---

#### Mastodon (7 errors)

Source: https://docs.joinmastodon.org/methods/statuses/

Match on HTTP status code and error message text.

| Status / Text | Message | Category |
|---|---|---|
| HTTP 401 | Mastodon token is invalid. Please reconnect. | TokenExpiredException |
| HTTP 403 | This action is not allowed. | Permission |
| HTTP 422 + `Text can't be blank` | Post text is required when no media is attached. | ContentPolicy |
| HTTP 422 + media error | Media validation failed. | MediaFormat |
| HTTP 413 | File is too large for this Mastodon instance. | MediaFormat |
| HTTP 429 | Rate limit exceeded. Please try again later. | RateLimit |
| HTTP 503 | Mastodon server error. Please try again. | ServerError |

## Integration with Publishers

Each publisher's `handleApiError` method is replaced with the platform exception:

```php
// Before (every publisher):
private function handleApiError(Response $response, string $context): void
{
    $body = $response->json() ?? [];
    $error = $body['error'] ?? [];
    // ... manual token check ...
    throw new \Exception("{$context}: {$message}");
}

// After:
private function handleApiError(Response $response): never
{
    // fromApiResponse handles token errors internally
    // (throws TokenExpiredException for token issues)
    throw InstagramPublishException::fromApiResponse($response);
}
```

## Integration with PublishToSocialPlatform Job

```php
try {
    $result = $publisher->publish($this->postPlatform);
    $this->postPlatform->markAsPublished(...);
} catch (TokenExpiredException $e) {
    Log::error('Token expired while publishing', [
        'post_platform_id' => $this->postPlatform->id,
        'platform' => $this->postPlatform->platform->value,
        'error' => $e->getMessage(),
    ]);
    $this->postPlatform->markAsFailed($e->getMessage());
    $this->postPlatform->socialAccount->markAsDisconnected($e->getMessage());
} catch (SocialPublishException $e) {
    // context() is automatically included in the log by Laravel
    Log::error('Social publish failed: ' . $e->userMessage);
    $this->postPlatform->markAsFailed($e->userMessage);
} catch (\Throwable $e) {
    Log::error('Unexpected publish error', [
        'post_platform_id' => $this->postPlatform->id,
        'error' => $e->getMessage(),
    ]);
    $this->postPlatform->markAsFailed($e->getMessage());
}
```

The `$e->userMessage` goes to `error_message` in the database (shown to user). The `context()` method automatically provides platform, category, error code, and raw response to Nightwatch/logs.

## Testing

Each platform exception gets a test file that verifies:
- Known error codes map to correct user messages and categories
- Token errors correctly throw `TokenExpiredException` (not `SocialPublishException`)
- Unknown errors fall through to generic message with `ErrorCategory::Unknown`

## Files Changed

- Create: `app/Exceptions/Social/ErrorCategory.php`
- Create: `app/Exceptions/Social/SocialPublishException.php`
- Create: 10 platform exception files (`InstagramPublishException.php`, etc.)
- Modify: 10 publisher files (replace `handleApiError` with platform exception)
- Modify: `app/Jobs/PublishToSocialPlatform.php` (add `SocialPublishException` catch)
- Create: 10 test files for platform exceptions
