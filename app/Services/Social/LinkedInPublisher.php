<?php

namespace App\Services\Social;

use App\Models\PostPlatform;
use Illuminate\Support\Facades\Http;

class LinkedInPublisher
{
    public function publish(PostPlatform $postPlatform): array
    {
        $account = $postPlatform->socialAccount;
        $accessToken = $account->access_token;

        $personUrn = "urn:li:person:{$account->platform_user_id}";

        $payload = [
            'author' => $personUrn,
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
            throw new \Exception("LinkedIn API error: " . $response->body());
        }

        $data = $response->json();

        return [
            'id' => $data['id'] ?? 'unknown',
            'url' => null,
        ];
    }
}
