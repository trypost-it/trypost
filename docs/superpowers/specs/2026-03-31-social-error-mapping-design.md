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

### Error Maps Per Platform (from Postiz + API docs)

**Instagram** (30 errors): See example above.

**TikTok** (18 errors):
- `access_token_invalid` -> TokenExpiredException
- `scope_not_authorized`, `scope_permission_missed` -> Permission
- `rate_limit_exceeded`, `reached_active_user_cap` -> RateLimit
- `file_format_check_failed`, `duration_check_failed`, `frame_rate_check_failed`, `picture_size_check_failed` -> MediaFormat
- `video_pull_failed`, `photo_pull_failed` -> ServerError
- `spam_risk_*` (5 variants) -> ContentPolicy
- `app_version_check_failed`, `unaudited_client_can_only_post_to_private_accounts` -> Permission
- `invalid_file_upload`, `invalid_params` -> MediaFormat
- `privacy_level_option_mismatch`, `url_ownership_unverified` -> Permission
- `internal` -> ServerError

**YouTube** (7 errors):
- `invalidTitle` -> ContentPolicy
- `failedPrecondition` -> MediaFormat (thumbnail too large)
- `uploadLimitExceeded` -> RateLimit
- `youtubeSignupRequired` -> Permission
- `youtube.thumbnail` -> Permission (unverified account)
- `Unauthorized`, `UNAUTHENTICATED`, `invalid_grant` -> TokenExpiredException

**Facebook** (13 errors):
- `Error validating access token`, `490`, `REVOKED_ACCESS_TOKEN`, `1404078` -> TokenExpiredException
- `1366046` -> ContentPolicy (reels need video)
- `1390008` -> ContentPolicy (caption too long)
- `1346003` -> ContentPolicy (thumbnail incompatible)
- `1404006`, `1404102`, `1404112` -> ServerError (upload failures)
- `1609008`, `1609010` -> MediaFormat (video format/reel encoding)
- `2061006` -> ContentPolicy (video too short)
- `1349125` -> RateLimit
- `1363047` -> MediaFormat (reel encoding issue)
- `Name parameter too long` -> ContentPolicy

**LinkedIn** (2 errors):
- `Unable to obtain activity` -> ServerError
- `resource is forbidden` -> Permission

**X/Twitter** (3 errors):
- `Unsupported Authentication` -> TokenExpiredException
- `usage-capped` -> RateLimit
- `duplicate-rules`, `invalid URL`, `video longer than 2 minutes` -> ContentPolicy

**Threads, Pinterest, Bluesky, Mastodon**: Postiz has no specific error maps for these. Start with generic parsing of their error responses and add specific codes as we encounter them in production.

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
