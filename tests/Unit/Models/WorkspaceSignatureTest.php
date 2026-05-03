<?php

declare(strict_types=1);

use App\Models\Workspace;
use App\Models\WorkspaceSignature;

test('workspace signature belongs to workspace', function () {
    $workspace = Workspace::factory()->create();
    $signature = WorkspaceSignature::factory()->create(['workspace_id' => $workspace->id]);

    expect($signature->workspace->id)->toBe($workspace->id);
});

test('workspace signature has fillable attributes', function () {
    $workspace = Workspace::factory()->create();
    $signature = WorkspaceSignature::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Marketing',
        'content' => '#marketing #digital #social',
    ]);

    expect($signature->name)->toBe('Marketing');
    expect($signature->content)->toBe('#marketing #digital #social');
});

test('workspace signature uses soft deletes', function () {
    $signature = WorkspaceSignature::factory()->create();
    $signatureId = $signature->id;

    $signature->delete();

    expect(WorkspaceSignature::find($signatureId))->toBeNull();
    expect(WorkspaceSignature::withTrashed()->find($signatureId))->not->toBeNull();
});
