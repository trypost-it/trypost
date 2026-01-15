<?php

namespace App\Services\Social;

use App\Models\PostPlatform;
use Illuminate\Support\Facades\Http;

class LinkedInPagePublisher
{
    public function publish(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;
        $accessToken = $account->access_token;

        $organizationId = $account->meta['organization_id'] ?? null;

        if (! $organizationId) {
            throw new \Exception('LinkedIn Page organization ID not configured');
        }

        $organizationUrn = "urn:li:organization:{$organizationId}";

        $payload = [
            'author' => $organizationUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'text' => $postPlatform->content,
                    ],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        $media = $postPlatform->media;

        if ($media->isNotEmpty()) {
            $firstMedia = $media->first();

            if ($firstMedia->type->value === 'image') {
                $payload['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'IMAGE';
                $payload['specificContent']['com.linkedin.ugc.ShareContent']['media'] = [
                    [
                        'status' => 'READY',
                        'originalUrl' => $firstMedia->url,
                    ],
                ];
            }
        }

        $response = Http::withToken($accessToken)
            ->post('https://api.linkedin.com/v2/ugcPosts', $payload);

        if ($response->failed()) {
            throw new \Exception("LinkedIn Page API error: " . $response->body());
        }

        $data = $response->json();

        return [
            'id' => $data['id'] ?? 'unknown',
            'url' => null,
        ];
    }
}
