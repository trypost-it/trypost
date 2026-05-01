<?php

declare(strict_types=1);

use App\Actions\PostComment\NotifyMentions;
use App\Enums\Notification\Channel;
use App\Enums\Notification\Type;
use App\Enums\UserWorkspace\Role;
use App\Jobs\SendNotification;
use App\Mail\MentionedInComment;
use App\Models\Notification;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspacePresence;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Mail::fake();
    Queue::fake();

    $this->author = User::factory()->create();
    $this->mentioned = User::factory()->create();
    $this->stranger = User::factory()->create();

    $this->workspace = Workspace::factory()->create(['user_id' => $this->author->id]);
    $this->workspace->members()->attach($this->author->id, ['role' => Role::Member->value]);
    $this->workspace->members()->attach($this->mentioned->id, ['role' => Role::Member->value]);

    $this->author->update(['current_workspace_id' => $this->workspace->id]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->author->id,
    ]);
});

test('notifies a workspace member mentioned in the comment body', function () {
    $comment = PostComment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->author->id,
        'body' => "Hey @[{$this->mentioned->id}] could you take a look?",
    ]);

    NotifyMentions::execute($comment);

    Queue::assertPushed(SendNotification::class, fn ($job) => $job->user->id === $this->mentioned->id
        && $job->type === Type::MentionedInComment
    );
});

test('does not notify the comment author when self-mentioning', function () {
    $comment = PostComment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->author->id,
        'body' => "Note for myself @[{$this->author->id}]",
    ]);

    NotifyMentions::execute($comment);

    Queue::assertNotPushed(SendNotification::class);
});

test('does not notify users that are not workspace members', function () {
    $comment = PostComment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->author->id,
        'body' => "FYI @[{$this->stranger->id}]",
    ]);

    NotifyMentions::execute($comment);

    Queue::assertNotPushed(SendNotification::class);
});

test('on update only notifies newly added mentions', function () {
    $comment = PostComment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->author->id,
        'body' => "Hi @[{$this->mentioned->id}]",
    ]);

    $secondMember = User::factory()->create();
    $this->workspace->members()->attach($secondMember->id, ['role' => Role::Member->value]);

    $previousBody = $comment->body;
    $comment->update(['body' => "Hi @[{$this->mentioned->id}] and @[{$secondMember->id}]"]);

    NotifyMentions::execute($comment, $previousBody);

    Queue::assertPushed(SendNotification::class, 1);
    Queue::assertPushed(
        SendNotification::class,
        fn ($job) => $job->user->id === $secondMember->id
    );
});

test('dedupes repeated mentions of the same user', function () {
    $comment = PostComment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->author->id,
        'body' => "@[{$this->mentioned->id}] @[{$this->mentioned->id}] @[{$this->mentioned->id}]",
    ]);

    NotifyMentions::execute($comment);

    Queue::assertPushed(SendNotification::class, 1);
});

test('with no mention markers no jobs are queued', function () {
    $comment = PostComment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->author->id,
        'body' => 'Plain comment with no mention',
    ]);

    NotifyMentions::execute($comment);

    Queue::assertNotPushed(SendNotification::class);
});

test('online recipient (workspace presence) gets InApp only — no mailable', function () {
    WorkspacePresence::markOnline($this->workspace->id, $this->mentioned->id);

    $comment = PostComment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->author->id,
        'body' => "Hey @[{$this->mentioned->id}]",
    ]);

    NotifyMentions::execute($comment);

    Queue::assertPushed(SendNotification::class, function ($job) {
        expect($job->channel)->toBe(Channel::InApp);
        expect($job->mailable)->toBeNull();

        return true;
    });
});

test('offline recipient gets Both (in-app + email)', function () {
    $comment = PostComment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->author->id,
        'body' => "Hey @[{$this->mentioned->id}]",
    ]);

    NotifyMentions::execute($comment);

    Queue::assertPushed(SendNotification::class, function ($job) {
        expect($job->channel)->toBe(Channel::Both);
        expect($job->mailable)->toBeInstanceOf(MentionedInComment::class);

        return true;
    });
});

test('respects mentioned_in_comment preference: when disabled, no email is queued', function () {
    $this->mentioned->notificationPreference()->create([
        'post_published' => true,
        'post_failed' => true,
        'account_disconnected' => true,
        'mentioned_in_comment' => false,
    ]);

    $comment = PostComment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->author->id,
        'body' => "Hey @[{$this->mentioned->id}]",
    ]);

    NotifyMentions::execute($comment);

    Queue::assertPushed(SendNotification::class, function ($job) {
        $job->handle();

        return true;
    });

    // In-app notification still saved (Channel::Both, but email path is gated by user preference)
    expect(Notification::where('user_id', $this->mentioned->id)
        ->where('type', Type::MentionedInComment)
        ->count())->toBe(1);

    Mail::assertNothingQueued();
});

test('processed job persists a Notification row + sends the mailable', function () {
    $comment = PostComment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->author->id,
        'body' => "Hey @[{$this->mentioned->id}]",
    ]);

    NotifyMentions::execute($comment);

    Queue::assertPushed(SendNotification::class, function ($job) {
        $job->handle();

        return true;
    });

    expect(Notification::where('user_id', $this->mentioned->id)
        ->where('type', Type::MentionedInComment)
        ->count())->toBe(1);

    Mail::assertQueued(MentionedInComment::class);
});
