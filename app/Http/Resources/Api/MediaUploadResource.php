<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Response shape for an MCP signed-URL upload (POST /api/uploads/{token}).
 *
 * @mixin Media
 */
class MediaUploadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'upload_token' => $this->upload_token,
            'media_id' => $this->id,
            'type' => $this->type,
            'mime_type' => $this->mime_type,
            'original_filename' => $this->original_filename,
        ];
    }
}
