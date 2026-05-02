<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\Media\Type as MediaType;
use App\Enums\PostPlatform\ContentType;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ContentTypeCompatibleWithMedia implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $contentType = ContentType::tryFrom((string) $value);
        if (! $contentType) {
            return;
        }

        $media = (array) data_get($this->data, 'media', []);
        $count = count($media);

        if ($contentType->requiresMedia() && $count === 0) {
            $fail("{$contentType->label()} requires at least one media file.");

            return;
        }

        if ($count === 0) {
            return;
        }

        $hasImage = collect($media)->contains(fn ($item) => $this->isImage((array) $item));
        $hasVideo = collect($media)->contains(fn ($item) => $this->isVideo((array) $item));

        if ($hasImage && ! $contentType->supportsImage()) {
            $fail("{$contentType->label()} does not support images.");
        }

        if ($hasVideo && ! $contentType->supportsVideo()) {
            $fail("{$contentType->label()} does not support videos.");
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isImage(array $item): bool
    {
        if (data_get($item, 'type') === MediaType::Image->value) {
            return true;
        }

        return str_starts_with((string) data_get($item, 'mime_type', ''), 'image/');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isVideo(array $item): bool
    {
        if (data_get($item, 'type') === MediaType::Video->value) {
            return true;
        }

        return str_starts_with((string) data_get($item, 'mime_type', ''), 'video/');
    }
}
