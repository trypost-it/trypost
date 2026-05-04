<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\PostPlatform\ContentType;
use App\Models\PostPlatform;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Cross-validates platforms[].content_type against the platform of the
 * post_platform row identified by platforms[].id — used on UPDATE flows
 * where the social_account is implicit (already attached to the post).
 */
class ContentTypeMatchesPostPlatform implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $idKey = preg_replace('/\.content_type$/', '.id', $attribute);
        $postPlatformId = data_get($this->data, $idKey);

        if (! $postPlatformId) {
            return;
        }

        $contentType = ContentType::tryFrom((string) $value);
        $postPlatform = PostPlatform::with('socialAccount')->find($postPlatformId);

        if (! $contentType || ! $postPlatform || ! $postPlatform->socialAccount) {
            return;
        }

        if (! in_array($postPlatform->socialAccount->platform, $contentType->compatiblePlatforms(), true)) {
            $fail(sprintf(
                'content_type "%s" is not compatible with the %s account.',
                $contentType->value,
                $postPlatform->socialAccount->platform->label(),
            ));
        }
    }
}
