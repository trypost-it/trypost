<?php

declare(strict_types=1);

use App\Models\Workspace;

/**
 * Regression test for a bug where Media rows ended up with
 * `mediable_type = "App\Models\Workspace"` (FQCN) instead of the
 * morphMap alias `"workspace"`. The bug source was code that did
 *   `$media->mediable_type = Workspace::class;`
 * which bypasses the morph map. Using the relationship
 * (`$workspace->media()->create([...])` or
 * `$media->mediable()->associate($workspace)`) is the correct path.
 */
test('Workspace::media()->create persists the morphMap alias, not the FQCN', function () {
    $workspace = Workspace::factory()->create();

    $media = $workspace->media()->create([
        'collection' => 'ai-generated',
        'type' => 'image',
        'path' => 'test/dummy.webp',
        'original_filename' => 'dummy.webp',
        'mime_type' => 'image/webp',
        'size' => 100,
        'order' => 0,
    ]);

    expect($media->mediable_type)->toBe('workspace');
    expect($media->mediable_type)->not->toBe(Workspace::class);
    expect($media->mediable->is($workspace))->toBeTrue();
});
