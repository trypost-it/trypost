<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Services\Post\PostPreviewer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps a Post with per-platform sanitized previews. Sanitization is
 * delegated to PostPreviewer (which calls ContentSanitizer).
 */
class PostPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return app(PostPreviewer::class)->forPost($this->resource);
    }
}
