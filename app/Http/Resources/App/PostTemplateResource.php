<?php

declare(strict_types=1);

namespace App\Http\Resources\App;

use App\Services\PostTemplate\PostTemplateData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostTemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var PostTemplateData $template */
        $template = $this->resource;

        return [
            'slug' => $template->slug,
            'name' => $template->name,
            'description' => $template->description,
            'category' => $template->category,
            'platform' => $template->platform,
            'content' => $template->content,
            'slides' => $template->slides,
            'image_count' => $template->imageCount,
            'image_keywords' => $template->imageKeywords,
        ];
    }
}
