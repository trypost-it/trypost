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
        public ?string $brandColor = null,
        public ?string $backgroundColor = null,
        public ?string $textColor = null,
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
            // Prefer deterministically-extracted colors (theme-color meta, CSS
            // custom properties, body rules) — the LLM only sees stripped
            // markdown so its color answers are unreliable.
            brandColor: $this->brandColor ?: ($llm->brandColor ?: null),
            backgroundColor: $this->backgroundColor ?: ($llm->backgroundColor ?: null),
            textColor: $this->textColor ?: ($llm->textColor ?: null),
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
            brandColor: $this->brandColor,
            backgroundColor: $this->backgroundColor,
            textColor: $this->textColor,
        );
    }

    public function withBrandColor(?string $brandColor): self
    {
        return new self(
            name: $this->name,
            description: $this->description,
            language: $this->language,
            tone: $this->tone,
            voiceNotes: $this->voiceNotes,
            logoUrl: $this->logoUrl,
            brandColor: $brandColor,
            backgroundColor: $this->backgroundColor,
            textColor: $this->textColor,
        );
    }

    /**
     * @return array{name: ?string, brand_description: ?string, content_language: ?string, brand_tone: ?string, brand_voice_notes: ?string, brand_color: ?string, background_color: ?string, text_color: ?string, logo_url: ?string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'brand_description' => $this->description,
            'content_language' => $this->language,
            'brand_tone' => $this->tone,
            'brand_voice_notes' => $this->voiceNotes,
            'brand_color' => $this->brandColor,
            'background_color' => $this->backgroundColor,
            'text_color' => $this->textColor,
            'logo_url' => $this->logoUrl,
        ];
    }
}
