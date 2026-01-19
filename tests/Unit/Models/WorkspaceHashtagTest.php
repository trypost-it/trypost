<?php

use App\Models\Workspace;
use App\Models\WorkspaceHashtag;

test('workspace hashtag belongs to workspace', function () {
    $workspace = Workspace::factory()->create();
    $hashtag = WorkspaceHashtag::factory()->create(['workspace_id' => $workspace->id]);

    expect($hashtag->workspace->id)->toBe($workspace->id);
});

test('workspace hashtag has fillable attributes', function () {
    $workspace = Workspace::factory()->create();
    $hashtag = WorkspaceHashtag::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Marketing',
        'hashtags' => '#marketing #digital #social',
    ]);

    expect($hashtag->name)->toBe('Marketing');
    expect($hashtag->hashtags)->toBe('#marketing #digital #social');
});

test('workspace hashtag uses soft deletes', function () {
    $hashtag = WorkspaceHashtag::factory()->create();
    $hashtagId = $hashtag->id;

    $hashtag->delete();

    expect(WorkspaceHashtag::find($hashtagId))->toBeNull();
    expect(WorkspaceHashtag::withTrashed()->find($hashtagId))->not->toBeNull();
});
