<?php

declare(strict_types=1);

use App\Ai\Agents\PostContentReviewer;
use App\Models\Workspace;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

test('instructions render brand context and language', function () {
    $workspace = Workspace::factory()->make([
        'name' => 'TryPost',
        'brand_tone' => 'friendly',
        'brand_voice_notes' => 'Use plain English.',
        'content_language' => 'pt-BR',
    ]);

    $agent = new PostContentReviewer(workspace: $workspace);
    $instructions = $agent->instructions();

    expect($instructions)->toContain('TryPost');
    expect($instructions)->toContain('friendly');
    expect($instructions)->toContain('plain English');
    expect($instructions)->toContain('pt-BR');
});

test('schema requires suggestions array with original/suggestion/reason', function () {
    $workspace = Workspace::factory()->make();
    $agent = new PostContentReviewer(workspace: $workspace);

    $schema = $agent->schema(new JsonSchemaTypeFactory);
    expect($schema)->toHaveKey('suggestions');
});
