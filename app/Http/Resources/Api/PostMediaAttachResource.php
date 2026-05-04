<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps a Post with the result of an attach-media operation
 * (counts + list of failed URLs).
 */
class PostMediaAttachResource extends JsonResource
{
    /**
     * @param  array{attached: array<int, array<string, mixed>>, failed: array<int, string>}  $result
     */
    public function __construct(Post $post, private readonly array $result)
    {
        parent::__construct($post);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'post' => (new PostResource($this->resource))->resolve(),
            'attached_count' => count($this->result['attached']),
            'failed_urls' => $this->result['failed'],
        ];
    }
}
