<?php

declare(strict_types=1);

use App\Models\PostTemplate;
use App\Services\Ai\TemplateContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('returns templates filtered by platform', function () {
    PostTemplate::factory()->count(3)->create(['platform' => 'instagram_carousel']);
    PostTemplate::factory()->count(2)->create(['platform' => 'linkedin_post']);

    $resolver = new TemplateContextResolver;
    $result = $resolver->relevantFor('instagram_carousel', 5);

    expect($result)->toHaveCount(3);
    expect($result->every(fn ($t) => $t->platform === 'instagram_carousel'))->toBeTrue();
});

test('respects limit', function () {
    PostTemplate::factory()->count(10)->create(['platform' => 'x_post']);

    $resolver = new TemplateContextResolver;
    $result = $resolver->relevantFor('x_post', 3);

    expect($result)->toHaveCount(3);
});

test('returns empty collection when no templates exist', function () {
    $resolver = new TemplateContextResolver;
    $result = $resolver->relevantFor('instagram_feed', 3);

    expect($result)->toHaveCount(0);
});

test('null platform returns all templates capped to limit', function () {
    PostTemplate::factory()->count(5)->create();

    $resolver = new TemplateContextResolver;
    $result = $resolver->relevantFor(null, 3);

    expect($result)->toHaveCount(3);
});
