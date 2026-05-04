<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Services\Post\PostMetricsFetcher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps a Post with its per-platform engagement metrics. The actual fetching
 * (with cache + per-platform dispatch) is delegated to PostMetricsFetcher.
 */
class PostMetricsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'post_id' => $this->id,
            'platforms' => app(PostMetricsFetcher::class)->forPost($this->resource)->all(),
        ];
    }
}
