<?php

declare(strict_types=1);

namespace App\Services\Brand;

use ArrayAccess;

final readonly class LlmBrandAnalysis
{
    public function __construct(
        public string $name = '',
        public string $description = '',
        public string $tone = '',
        public string $language = '',
        public string $voiceNotes = '',
    ) {}

    public static function fromResponse(ArrayAccess|array $response): self
    {
        return new self(
            name: trim((string) data_get($response, 'name', '')),
            description: trim((string) data_get($response, 'description', '')),
            tone: trim((string) data_get($response, 'tone', '')),
            language: trim((string) data_get($response, 'language', '')),
            voiceNotes: trim((string) data_get($response, 'voice_notes', '')),
        );
    }
}
