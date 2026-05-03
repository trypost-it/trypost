<?php

declare(strict_types=1);

namespace App\Services\PostTemplate;

final readonly class PostTemplateData
{
    /**
     * @param  array<int, string>|null  $imageKeywords
     * @param  array<int, array{title: string, body: string, image_keywords?: array<int, string>}>|null  $slides
     */
    public function __construct(
        public string $slug,
        public string $platform,
        public string $category,
        public string $name,
        public ?string $description,
        public string $content,
        public int $imageCount,
        public ?array $imageKeywords,
        public ?array $slides,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $platform): self
    {
        return new self(
            slug: (string) data_get($data, 'slug'),
            platform: $platform,
            category: (string) data_get($data, 'category'),
            name: (string) data_get($data, 'name'),
            description: data_get($data, 'description'),
            content: (string) data_get($data, 'content'),
            imageCount: (int) data_get($data, 'image_count', 0),
            imageKeywords: data_get($data, 'image_keywords'),
            slides: data_get($data, 'slides'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'platform' => $this->platform,
            'category' => $this->category,
            'name' => $this->name,
            'description' => $this->description,
            'content' => $this->content,
            'image_count' => $this->imageCount,
            'image_keywords' => $this->imageKeywords,
            'slides' => $this->slides,
        ];
    }
}
