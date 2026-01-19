<?php

use App\Models\Workspace;
use App\Models\WorkspaceLabel;

test('workspace label belongs to workspace', function () {
    $workspace = Workspace::factory()->create();
    $label = WorkspaceLabel::factory()->create(['workspace_id' => $workspace->id]);

    expect($label->workspace->id)->toBe($workspace->id);
});

test('workspace label has fillable attributes', function () {
    $workspace = Workspace::factory()->create();
    $label = WorkspaceLabel::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Urgent',
        'color' => '#ff0000',
    ]);

    expect($label->name)->toBe('Urgent');
    expect($label->color)->toBe('#ff0000');
});

test('workspace label uses soft deletes', function () {
    $label = WorkspaceLabel::factory()->create();
    $labelId = $label->id;

    $label->delete();

    expect(WorkspaceLabel::find($labelId))->toBeNull();
    expect(WorkspaceLabel::withTrashed()->find($labelId))->not->toBeNull();
});
