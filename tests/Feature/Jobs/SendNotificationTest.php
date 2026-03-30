<?php

declare(strict_types=1);

use App\Enums\Notification\Channel;
use App\Enums\Notification\Type;
use App\Jobs\SendNotification;
use App\Mail\PostPublishFailed;
use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
});

test('send notification creates in-app notification for in_app channel', function () {
    (new SendNotification(
        user: $this->user,
        workspaceId: $this->workspace->id,
        type: Type::PostFailed,
        channel: Channel::InApp,
        title: 'Test title',
        body: 'Test body',
    ))->handle();

    expect(Notification::count())->toBe(1);

    $notification = Notification::first();
    expect($notification->user_id)->toBe($this->user->id);
    expect($notification->title)->toBe('Test title');
    expect($notification->type)->toBe(Type::PostFailed);

    Mail::assertNothingSent();
});

test('send notification sends email for email channel', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    (new SendNotification(
        user: $this->user,
        workspaceId: $this->workspace->id,
        type: Type::PostFailed,
        channel: Channel::Email,
        title: 'Test title',
        body: 'Test body',
        mailable: new PostPublishFailed($post),
    ))->handle();

    expect(Notification::count())->toBe(0);

    Mail::assertQueued(PostPublishFailed::class);
});

test('send notification creates notification and sends email for both channel', function () {
    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    (new SendNotification(
        user: $this->user,
        workspaceId: $this->workspace->id,
        type: Type::PostFailed,
        channel: Channel::Both,
        title: 'Test title',
        body: 'Test body',
        mailable: new PostPublishFailed($post),
    ))->handle();

    expect(Notification::count())->toBe(1);

    Mail::assertQueued(PostPublishFailed::class);
});

test('send notification stores data json', function () {
    (new SendNotification(
        user: $this->user,
        workspaceId: $this->workspace->id,
        type: Type::PostFailed,
        channel: Channel::InApp,
        title: 'Test',
        body: 'Body',
        data: ['post_id' => 'abc-123'],
    ))->handle();

    $notification = Notification::first();
    expect($notification->data)->toBe(['post_id' => 'abc-123']);
});
