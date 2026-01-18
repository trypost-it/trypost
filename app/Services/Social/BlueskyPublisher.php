<?php

namespace App\Services\Social;

use App\Exceptions\TokenExpiredException;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlueskyPublisher
{
    public function publish(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;
        $service = $account->meta['service'] ?? 'https://bsky.social';

        // Refresh token if needed
        if ($account->is_token_expired || $account->is_token_expiring_soon) {
            $this->refreshToken($account);
            $account->refresh();
        }

        $medias = $postPlatform->media;
        $embed = null;

        // Upload images if present (max 4)
        if ($medias->count() > 0) {
            $images = [];
            foreach ($medias->take(4) as $media) {
                if (str_starts_with($media->mime_type, 'image/')) {
                    $blob = $this->uploadBlob($account, $service, $media->url, $media->mime_type);
                    if ($blob) {
                        $images[] = [
                            'alt' => '',
                            'image' => $blob,
                        ];
                    }
                }
            }

            if (count($images) > 0) {
                $embed = [
                    '$type' => 'app.bsky.embed.images',
                    'images' => $images,
                ];
            }
        }

        // Parse facets (links, mentions, hashtags) from text
        $text = $postPlatform->content ?? '';
        $facets = $this->parseFacets($text);

        // Create post record
        $record = [
            '$type' => 'app.bsky.feed.post',
            'text' => $text,
            'createdAt' => now()->toIso8601ZuluString(),
        ];

        if ($embed) {
            $record['embed'] = $embed;
        }

        if (! empty($facets)) {
            $record['facets'] = $facets;
        }

        Log::info('Bluesky publishing post', [
            'user_id' => $account->platform_user_id,
            'has_embed' => $embed !== null,
            'facet_count' => count($facets),
        ]);

        $response = Http::withToken($account->access_token)
            ->post("{$service}/xrpc/com.atproto.repo.createRecord", [
                'repo' => $account->platform_user_id,
                'collection' => 'app.bsky.feed.post',
                'record' => $record,
            ]);

        if ($response->failed()) {
            Log::error('Bluesky post failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $this->handleApiError($response);
        }

        $data = $response->json();

        // Extract post ID from URI (at://did/app.bsky.feed.post/xxx)
        $uri = $data['uri'];
        $postId = basename($uri);

        Log::info('Bluesky post created successfully', [
            'uri' => $uri,
            'post_id' => $postId,
        ]);

        return [
            'id' => $postId,
            'url' => $this->buildPostUrl($account->username, $postId),
        ];
    }

    private function uploadBlob(SocialAccount $account, string $service, string $url, string $mimeType): ?array
    {
        try {
            $imageContent = file_get_contents($url);

            if ($imageContent === false) {
                Log::error('Bluesky failed to read image', ['url' => $url]);

                return null;
            }

            // Bluesky has 1MB limit for images
            if (strlen($imageContent) > 1000000) {
                Log::warning('Bluesky image exceeds 1MB limit', [
                    'size' => strlen($imageContent),
                    'url' => $url,
                ]);
                // TODO: Resize image if needed
            }

            $response = Http::withToken($account->access_token)
                ->withHeaders(['Content-Type' => $mimeType])
                ->withBody($imageContent, $mimeType)
                ->post("{$service}/xrpc/com.atproto.repo.uploadBlob");

            if ($response->failed()) {
                Log::error('Bluesky blob upload failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json()['blob'];
        } catch (\Exception $e) {
            Log::error('Bluesky blob upload exception', [
                'error' => $e->getMessage(),
                'url' => $url,
            ]);

            return null;
        }
    }

    private function parseFacets(string $text): array
    {
        $facets = [];

        // Parse URLs
        preg_match_all(
            '/(https?:\/\/[^\s]+)/u',
            $text,
            $urlMatches,
            PREG_OFFSET_CAPTURE
        );

        foreach ($urlMatches[0] as $match) {
            $url = $match[0];
            $start = $this->getUtf8ByteOffset($text, $match[1]);
            $end = $start + strlen($url);

            $facets[] = [
                'index' => [
                    'byteStart' => $start,
                    'byteEnd' => $end,
                ],
                'features' => [
                    [
                        '$type' => 'app.bsky.richtext.facet#link',
                        'uri' => $url,
                    ],
                ],
            ];
        }

        // Parse mentions (@handle.bsky.social)
        preg_match_all(
            '/@([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?/u',
            $text,
            $mentionMatches,
            PREG_OFFSET_CAPTURE
        );

        foreach ($mentionMatches[0] as $match) {
            $mention = $match[0];
            $handle = substr($mention, 1); // Remove @
            $start = $this->getUtf8ByteOffset($text, $match[1]);
            $end = $start + strlen($mention);

            $facets[] = [
                'index' => [
                    'byteStart' => $start,
                    'byteEnd' => $end,
                ],
                'features' => [
                    [
                        '$type' => 'app.bsky.richtext.facet#mention',
                        'did' => $handle, // Will be resolved by Bluesky
                    ],
                ],
            ];
        }

        // Parse hashtags (#tag)
        preg_match_all(
            '/#[^\s\p{P}]+/u',
            $text,
            $hashtagMatches,
            PREG_OFFSET_CAPTURE
        );

        foreach ($hashtagMatches[0] as $match) {
            $hashtag = $match[0];
            $tag = substr($hashtag, 1); // Remove #
            $start = $this->getUtf8ByteOffset($text, $match[1]);
            $end = $start + strlen($hashtag);

            $facets[] = [
                'index' => [
                    'byteStart' => $start,
                    'byteEnd' => $end,
                ],
                'features' => [
                    [
                        '$type' => 'app.bsky.richtext.facet#tag',
                        'tag' => $tag,
                    ],
                ],
            ];
        }

        return $facets;
    }

    private function getUtf8ByteOffset(string $text, int $charOffset): int
    {
        return strlen(substr($text, 0, $charOffset));
    }

    private function buildPostUrl(string $handle, string $postId): string
    {
        return "https://bsky.app/profile/{$handle}/post/{$postId}";
    }

    public function refreshToken(SocialAccount $account): void
    {
        $service = $account->meta['service'] ?? 'https://bsky.social';

        Log::info('Bluesky refreshing token', ['user_id' => $account->platform_user_id]);

        // Try refresh first
        $response = Http::withToken($account->refresh_token)
            ->post("{$service}/xrpc/com.atproto.server.refreshSession");

        if ($response->successful()) {
            $data = $response->json();
            $account->update([
                'access_token' => $data['accessJwt'],
                'refresh_token' => $data['refreshJwt'],
                'token_expires_at' => now()->addHours(2),
            ]);

            Log::info('Bluesky token refreshed via refresh token');

            return;
        }

        Log::warning('Bluesky refresh token failed, trying re-authentication', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        // If refresh fails, re-authenticate with stored credentials
        if (isset($account->meta['password'])) {
            try {
                $password = decrypt($account->meta['password']);
                $identifier = $account->meta['identifier'];

                $response = Http::post("{$service}/xrpc/com.atproto.server.createSession", [
                    'identifier' => $identifier,
                    'password' => $password,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $account->update([
                        'access_token' => $data['accessJwt'],
                        'refresh_token' => $data['refreshJwt'],
                        'token_expires_at' => now()->addHours(2),
                    ]);

                    Log::info('Bluesky token refreshed via re-authentication');

                    return;
                }
            } catch (\Exception $e) {
                Log::error('Bluesky re-authentication failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new TokenExpiredException('Bluesky session expired');
    }

    private function handleApiError(Response $response): void
    {
        $body = $response->json() ?? [];
        $error = $body['error'] ?? 'Unknown error';
        $message = $body['message'] ?? $response->body();

        if ($error === 'ExpiredToken' || $error === 'InvalidToken') {
            throw new TokenExpiredException("Bluesky: {$message}");
        }

        throw new \Exception("Bluesky API error: {$message}");
    }
}
