<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostPlatformResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform?->value,
            'content_type' => $this->content_type?->value,
            'status' => $this->status?->value,
            'enabled' => $this->enabled,
            'social_account' => new SocialAccountResource($this->whenLoaded('socialAccount')),
        ];
    }
}
