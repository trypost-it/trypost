<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostTemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'platform' => $this->platform,
            'content' => $this->content,
            'slides' => $this->slides,
            'image_count' => $this->image_count,
            'image_keywords' => $this->image_keywords,
        ];
    }
}
