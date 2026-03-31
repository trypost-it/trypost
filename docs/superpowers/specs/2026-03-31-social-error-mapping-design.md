# Social Platform Error Mapping

## Problem

All publishers throw generic `\Exception` or `TokenExpiredException` with raw API error messages. Users see cryptic strings like `"Instagram API error: Only photo or video can be accepted as media type."` instead of actionable messages. Debugging requires reading raw logs.

## Solution

Create per-platform exception classes inside `app/Exceptions/Social/` that parse API responses and return clear user-facing messages, a categorized error type, and structured log data for Nightwatch.

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

```php
abstract class SocialPublishException extends \Exception
{
    public function __construct(
        public readonly string $userMessage,
        public readonly ErrorCategory $category,
        public readonly ?string $platformErrorCode = null,
        public readonly ?string $rawResponse = null,
    ) {
        parent::__construct($userMessage);
    }

    abstract public static function fromApiResponse(mixed $response): static;

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

    abstract protected static function platform(): string;
}
```

### Per-Platform Exception (Example: Instagram)

`fromApiResponse` receives the HTTP response and matches error codes to user-friendly messages. Each mapping also assigns a category.

```php
class InstagramPublishException extends SocialPublishException
{
    protected static function platform(): string { return 'instagram'; }

    public static function fromApiResponse(mixed $response): static
    {
        $body = $response->body();
        $json = $response->json() ?? [];
        $errorCode = $json['error']['code'] ?? null;
        $errorSubcode = $json['error']['error_subcode'] ?? null;

        [$message, $category, $code] = match(true) {
            // Token errors -> throw TokenExpiredException instead
            // (handled before calling this method)

            // Media format errors
            str_contains($body, '2207026') => ['Unsupported video format', ErrorCategory::MediaFormat, '2207026'],
            str_contains($body, '2207005') => ['Unsupported image format', ErrorCategory::MediaFormat, '2207005'],
            str_contains($body, '2207004') => ['Image is too large', ErrorCategory::MediaFormat, '2207004'],
            str_contains($body, '2207009') => ['Aspect ratio not supported (must be between 4:5 and 1.91:1)', ErrorCategory::MediaFormat, '2207009'],
            str_contains($body, '36003') => ['Aspect ratio not supported (must be between 4:5 and 1.91:1)', ErrorCategory::MediaFormat, '36003'],
            str_contains($body, '36001') => ['Image resolution too high (max 1920x1080)', ErrorCategory::MediaFormat, '36001'],
            str_contains($body, '2207057') => ['Invalid thumbnail offset for video', ErrorCategory::MediaFormat, '2207057'],
            str_contains($body, '2207023') => ['Unknown media type', ErrorCategory::MediaFormat, '2207023'],

            // Upload/processing errors
            str_contains($body, '2207003') => ['Timeout downloading media, please try again', ErrorCategory::ServerError, '2207003'],
            str_contains($body, '2207020') => ['Media expired, please upload again', ErrorCategory::ServerError, '2207020'],
            str_contains($body, '2207032') => ['Failed to create media, please try again', ErrorCategory::ServerError, '2207032'],
            str_contains($body, '2207053') => ['Unknown upload error, please try again', ErrorCategory::ServerError, '2207053'],
            str_contains($body, '2207052') => ['Media fetch failed, please try again', ErrorCategory::ServerError, '2207052'],
            str_contains($body, '2207006') => ['Media not found, please upload again', ErrorCategory::ServerError, '2207006'],
            str_contains($body, '2207008') => ['Media builder expired, please try again', ErrorCategory::ServerError, '2207008'],

            // Content validation
            str_contains($body, '2207010') => ['Caption is too long', ErrorCategory::ContentPolicy, '2207010'],
            str_contains($body, '2207028') => ['Carousel validation failed', ErrorCategory::ContentPolicy, '2207028'],
            str_contains($body, '2207081') => ["This account doesn't support Trial Reels", ErrorCategory::Permission, '2207081'],

            // Product tagging
            str_contains($body, '2207035') => ['Product tag positions not supported for videos', ErrorCategory::ContentPolicy, '2207035'],
            str_contains($body, '2207036') => ['Product tag positions required for photos', ErrorCategory::ContentPolicy, '2207036'],
            str_contains($body, '2207037') => ['Product tag validation failed', ErrorCategory::ContentPolicy, '2207037'],
            str_contains($body, '2207040') => ['Too many product tags', ErrorCategory::ContentPolicy, '2207040'],

            // Rate limits
            str_contains($body, 'Page request limit reached') => ['Daily posting limit reached, try again tomorrow', ErrorCategory::RateLimit, 'page_limit'],
            str_contains($body, '2207042') => ['Maximum of 25 posts per day reached', ErrorCategory::RateLimit, '2207042'],

            // Permissions
            str_contains($body, '2207050') => ['Instagram user is restricted', ErrorCategory::Permission, '2207050'],
            str_contains($body, 'Not enough permissions to post') => ['Missing permissions, please reconnect your account', ErrorCategory::Permission, 'permissions'],
            str_contains($body, '190,') => ['Account missing permissions, please reconnect and allow all permissions', ErrorCategory::Permission, '190'],
            str_contains($body, 'param collaborators is not allowed') => ['Collaborators are not allowed for carousel', ErrorCategory::ContentPolicy, 'collaborators'],

            // Content policy
            str_contains($body, '2207001') => ['Instagram detected spam, try different content', ErrorCategory::ContentPolicy, '2207001'],
            str_contains($body, '2207051') => ['Instagram blocked your request', ErrorCategory::ContentPolicy, '2207051'],
            str_contains($body, '2207027') => ['Unknown error, please try again later', ErrorCategory::Unknown, '2207027'],

            default => [$json['error']['message'] ?? 'Instagram publishing failed', ErrorCategory::Unknown, (string) $errorSubcode],
        };

        return new static($message, $category, $code, $body);
    }
}
```

### Error Maps Per Platform (from Postiz + API docs)

**TikTok** (18 errors):
- `access_token_invalid` -> TokenExpiredException
- `scope_not_authorized`, `scope_permission_missed` -> Permission
- `rate_limit_exceeded`, `reached_active_user_cap` -> RateLimit
- `file_format_check_failed`, `duration_check_failed`, `frame_rate_check_failed`, `picture_size_check_failed` -> MediaFormat
- `video_pull_failed`, `photo_pull_failed` -> ServerError
- `spam_risk_*` (5 variants) -> ContentPolicy
- `app_version_check_failed` -> Permission
- `invalid_file_upload`, `invalid_params` -> MediaFormat
- `privacy_level_option_mismatch`, `url_ownership_unverified`, `unaudited_client_can_only_post_to_private_accounts` -> Permission
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
- `1349125` -> RateLimit (rate limit exceeded)
- `1363047` -> MediaFormat (reel encoding issue)
- `Name parameter too long` -> ContentPolicy

**LinkedIn** (2 errors):
- `Unable to obtain activity` -> ServerError (retry)
- `resource is forbidden` -> Permission (retry)

**X/Twitter** (3 errors):
- `Unsupported Authentication` -> TokenExpiredException
- `usage-capped` -> RateLimit
- `duplicate-rules`, `invalid URL`, `video longer than 2 minutes` -> ContentPolicy

**Threads, Pinterest, Bluesky, Mastodon**: Postiz has no specific error maps. We will start with generic parsing of their error responses and add specific codes as we encounter them.

## Integration with Publishers

Each publisher's `handleApiError` method will be replaced:

```php
// Before:
private function handleApiError(Response $response, string $context): void
{
    // ... generic parsing ...
    throw new \Exception("{$context}: {$message}");
}

// After:
private function handleApiError(Response $response): void
{
    $body = $response->json() ?? [];
    $error = $body['error'] ?? [];

    // Check token errors first
    if ($this->isTokenError($response, $error)) {
        throw new TokenExpiredException(...);
    }

    // Throw platform-specific exception with mapped message
    throw InstagramPublishException::fromApiResponse($response);
}
```

## Integration with PublishToSocialPlatform Job

```php
try {
    $result = $publisher->publish($this->postPlatform);
    $this->postPlatform->markAsPublished(...);
} catch (TokenExpiredException $e) {
    // ... existing token handling ...
} catch (SocialPublishException $e) {
    Log::error('Social publish failed', $e->context());
    $this->postPlatform->markAsFailed($e->userMessage);
} catch (\Throwable $e) {
    Log::error('Unexpected publish error', [...]);
    $this->postPlatform->markAsFailed($e->getMessage());
}
```

The `$e->userMessage` goes to the database (shown to user). The `$e->context()` goes to logs/Nightwatch with full debugging info.

## Testing

Each platform exception gets a test that verifies:
- Known error codes map to correct user messages
- Known error codes map to correct categories
- Unknown errors fall through to generic message
- Token errors are still handled by TokenExpiredException (not caught by platform exception)

## Files Changed

- Create: `app/Exceptions/Social/ErrorCategory.php`
- Create: `app/Exceptions/Social/SocialPublishException.php`
- Create: 10 platform exception files
- Modify: 10 publisher files (replace `handleApiError` with platform exception)
- Modify: `app/Jobs/PublishToSocialPlatform.php` (add `SocialPublishException` catch)
- Create: 10 test files for platform exceptions
