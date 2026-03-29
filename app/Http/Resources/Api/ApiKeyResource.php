<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiKeyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'key_hint' => $this->key_hint,
            'status' => $this->status,
            'last_used_at' => $this->last_used_at?->format('Y-m-d H:i:s'),
            'expires_at' => $this->expires_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
