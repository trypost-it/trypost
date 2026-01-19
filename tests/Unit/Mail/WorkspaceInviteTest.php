<?php

use App\Mail\WorkspaceInvite;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvite as WorkspaceInviteModel;

test('workspace invite mail has correct subject', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['name' => 'Test Workspace']);
    $invite = WorkspaceInviteModel::factory()->create([
        'workspace_id' => $workspace->id,
        'invited_by' => $user->id,
    ]);

    $mail = new WorkspaceInvite($invite);

    expect($mail->envelope()->subject)->toBe("You've been invited to join Test Workspace");
});

test('workspace invite mail has correct content', function () {
    $user = User::factory()->create(['name' => 'John Doe']);
    $workspace = Workspace::factory()->create(['name' => 'My Team']);
    $invite = WorkspaceInviteModel::factory()->create([
        'workspace_id' => $workspace->id,
        'invited_by' => $user->id,
    ]);

    $mail = new WorkspaceInvite($invite);
    $content = $mail->content();

    expect($content->view)->toBe('mail.workspace-invite');
    expect($content->with['title'])->toBe("You've been invited to join My Team");
    expect($content->with['previewText'])->toContain('John Doe');
    expect($content->with['invite'])->toBe($invite);
    expect($content->with['url'])->toBe(route('invites.show', $invite->token));
});

test('workspace invite mail has no attachments', function () {
    $invite = WorkspaceInviteModel::factory()->create();

    $mail = new WorkspaceInvite($invite);

    expect($mail->attachments())->toBeEmpty();
});

test('workspace invite mail is queueable', function () {
    $invite = WorkspaceInviteModel::factory()->create();

    $mail = new WorkspaceInvite($invite);

    expect($mail)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});
