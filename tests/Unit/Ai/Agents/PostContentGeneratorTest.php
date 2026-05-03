<?php

declare(strict_types=1);

use App\Ai\Agents\PostContentGenerator;
use App\Models\PostTemplate;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

uses(RefreshDatabase::class);

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
    PostTemplate::factory()->create([
        'platform' => 'instagram_carousel',
        'name' => 'Test template',
        'content' => 'EXAMPLE CONTENT MARKER',
    ]);

    $agent = new PostContentGenerator(
        workspace: Workspace::factory()->make(),
        format: 'carousel',
        slideCount: 3,
        platformContext: 'instagram_carousel',
    );

    expect($agent->instructions())->toContain('EXAMPLE CONTENT MARKER');
});

test('instructions do not include examples section when platform context is null', function () {
    PostTemplate::factory()->count(3)->create(['platform' => 'instagram_carousel']);

    $agent = new PostContentGenerator(
        workspace: Workspace::factory()->make(),
        format: 'single',
    );

    expect($agent->instructions())->not->toContain('curated library');
});
