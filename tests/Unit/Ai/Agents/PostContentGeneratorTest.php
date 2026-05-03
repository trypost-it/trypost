<?php

declare(strict_types=1);

use App\Ai\Agents\PostContentGenerator;
use App\Models\Workspace;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

test('instructions render brand context', function () {
    $workspace = Workspace::factory()->make([
        'name' => 'TryPost',
        'brand_description' => 'Social media scheduling tool',
        'brand_tone' => 'friendly',
        'brand_voice_notes' => 'Use everyday language, no jargon.',
        'content_language' => 'en',
    ]);

    $agent = new PostContentGenerator(workspace: $workspace);
    $instructions = $agent->instructions();

    expect($instructions)->toContain('TryPost');
    expect($instructions)->toContain('friendly');
    expect($instructions)->toContain('everyday language');
    expect($instructions)->toContain('en');
});

test('instructions include current_content when provided', function () {
    $workspace = Workspace::factory()->make();
    $agent = new PostContentGenerator(
        workspace: $workspace,
        currentContent: 'Hello world',
    );

    expect($agent->instructions())->toContain('Hello world');
});

test('instructions omit current_content when not provided', function () {
    $workspace = Workspace::factory()->make();
    $agent = new PostContentGenerator(workspace: $workspace);

    expect($agent->instructions())->not->toContain('user already has this content');
});

test('single format schema returns content and image_keywords', function () {
    $workspace = Workspace::factory()->make();
    $agent = new PostContentGenerator(workspace: $workspace, format: 'single');

    $schemaFactory = new JsonSchemaTypeFactory;
    $schema = $agent->schema($schemaFactory);

    expect($schema)->toHaveKey('content');
    expect($schema)->toHaveKey('image_keywords');
    expect($schema)->not->toHaveKey('slides');
    expect($schema)->not->toHaveKey('caption');
});

test('carousel format schema returns caption and slides', function () {
    $workspace = Workspace::factory()->make();
    $agent = new PostContentGenerator(workspace: $workspace, format: 'carousel', slideCount: 5);

    $schemaFactory = new JsonSchemaTypeFactory;
    $schema = $agent->schema($schemaFactory);

    expect($schema)->toHaveKey('caption');
    expect($schema)->toHaveKey('slides');
    expect($schema)->not->toHaveKey('content');
});

test('carousel instructions mention slide count', function () {
    $workspace = Workspace::factory()->make();
    $agent = new PostContentGenerator(workspace: $workspace, format: 'carousel', slideCount: 5);

    $instructions = $agent->instructions();

    expect($instructions)->toContain('5');
    expect($instructions)->toContain('slides');
});

test('single instructions mention image_keywords', function () {
    $workspace = Workspace::factory()->make();
    $agent = new PostContentGenerator(workspace: $workspace, format: 'single');

    $instructions = $agent->instructions();

    expect($instructions)->toContain('image_keywords');
});

test('instructions include examples when platform context provided and templates exist', function () {
    $agent = new PostContentGenerator(
        workspace: Workspace::factory()->make(),
        format: 'carousel',
        slideCount: 3,
        platformContext: 'instagram_carousel',
    );

    // The registry ships with carousel templates whose content contains
    // distinctive snippets like "Swipe to see" — at least one should appear.
    $instructions = $agent->instructions();
    expect(
        str_contains($instructions, 'Swipe')
            || str_contains($instructions, 'carousel')
            || str_contains($instructions, '{{brand_name}}'),
    )->toBeTrue();
});

test('instructions do not include examples section when platform context is null', function () {
    $agent = new PostContentGenerator(
        workspace: Workspace::factory()->make(),
        format: 'single',
    );

    expect($agent->instructions())->not->toContain('curated library');
});
