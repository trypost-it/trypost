<?php

declare(strict_types=1);

use App\Services\Ai\TemplateContextResolver;
use App\Services\PostTemplate\Registry;

test('returns templates filtered by platform', function () {
    $resolver = new TemplateContextResolver(app(Registry::class));
    $result = $resolver->relevantFor('instagram_carousel', 10);

    expect($result)->not->toBeEmpty();
    expect($result->every(fn ($t) => $t->platform === 'instagram_carousel'))->toBeTrue();
});

test('respects limit', function () {
    $resolver = new TemplateContextResolver(app(Registry::class));
    $result = $resolver->relevantFor('linkedin_post', 2);

    expect($result->count())->toBeLessThanOrEqual(2);
});

test('returns empty collection for unknown platform', function () {
    $resolver = new TemplateContextResolver(app(Registry::class));
    $result = $resolver->relevantFor('platform_that_does_not_exist', 3);

    expect($result)->toHaveCount(0);
});

test('null platform returns templates from all platforms capped to limit', function () {
    $resolver = new TemplateContextResolver(app(Registry::class));
    $result = $resolver->relevantFor(null, 3);

    expect($result)->toHaveCount(3);
});
