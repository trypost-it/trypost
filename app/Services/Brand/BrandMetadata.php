<?php

declare(strict_types=1);

namespace App\Services\Brand;

final readonly class BrandMetadata
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?string $language = null,
        public ?string $tone = null,
        public ?string $voiceNotes = null,
        public ?string $logoUrl = null,
    ) {}

    public function mergeLlm(LlmBrandAnalysis $llm): self
    {
        return new self(
            name: $llm->name ?: $this->name,
            description: $llm->description ?: $this->description,
            language: $llm->language ?: $this->language,
            tone: $llm->tone ?: null,
            voiceNotes: $llm->voiceNotes ?: null,
            logoUrl: $this->logoUrl,
        );
    }

    public function withLogoUrl(?string $logoUrl): self
    {
        return new self(
            name: $this->name,
            description: $this->description,
            language: $this->language,
            tone: $this->tone,
            voiceNotes: $this->voiceNotes,
            logoUrl: $logoUrl,
        );
    }

    /**
     * @return array{name: ?string, brand_description: ?string, content_language: ?string, brand_tone: ?string, brand_voice_notes: ?string, logo_url: ?string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'brand_description' => $this->description,
            'content_language' => $this->language,
            'brand_tone' => $this->tone,
            'brand_voice_notes' => $this->voiceNotes,
            'logo_url' => $this->logoUrl,
        ];
    }
}
