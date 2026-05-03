<?php

declare(strict_types=1);

use App\Enums\Notification\Channel;
use App\Enums\Notification\Type;
use App\Enums\UserWorkspace\Role;
use App\Jobs\SendNotification;
use App\Mail\PostPublished;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->user = User::factory()->create([]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('notification preferences page requires authentication', function () {
    $response = $this->get(route('app.notifications.preferences'));

    $response->assertRedirect(route('login'));
});

test('notification preferences page renders', function () {
    $response = $this->actingAs($this->user)->get(route('app.notifications.preferences'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/profile/Notifications')
        ->has('preferences')
    );
});

test('notification preferences are created with defaults on first visit', function () {
    expect(NotificationPreference::where('user_id', $this->user->id)->count())->toBe(0);

    $this->actingAs($this->user)->get(route('app.notifications.preferences'));

    $preference = NotificationPreference::where('user_id', $this->user->id)->first();
    expect($preference)->not->toBeNull();
    expect($preference->post_published)->toBeTrue();
    expect($preference->post_failed)->toBeTrue();
    expect($preference->account_disconnected)->toBeTrue();
});

test('user can update notification preferences', function () {
    $response = $this->actingAs($this->user)->put(route('app.notifications.preferences.update'), [
        'post_published' => false,
        'post_failed' => true,
        'account_disconnected' => false,
    ]);

    $response->assertRedirect();

    $preference = NotificationPreference::where('user_id', $this->user->id)->first();
    expect($preference->post_published)->toBeFalse();
    expect($preference->post_failed)->toBeTrue();
    expect($preference->account_disconnected)->toBeFalse();
});

test('update validates boolean fields', function () {
    $response = $this->actingAs($this->user)->put(route('app.notifications.preferences.update'), [
        'post_published' => 'invalid',
        'post_failed' => true,
        'account_disconnected' => true,
    ]);

    $response->assertSessionHasErrors('post_published');
});

test('wantsEmailFor respects preferences', function () {
    NotificationPreference::create([
        'user_id' => $this->user->id,
        'post_published' => false,
        'post_failed' => true,
        'account_disconnected' => false,
    ]);

    expect($this->user->wantsEmailFor(Type::PostPublished))->toBeFalse();
    expect($this->user->wantsEmailFor(Type::PostFailed))->toBeTrue();
    expect($this->user->wantsEmailFor(Type::PostPartiallyPublished))->toBeTrue(); // maps to post_failed
    expect($this->user->wantsEmailFor(Type::AccountDisconnected))->toBeFalse();
});

test('wantsEmailFor defaults to true when no preferences exist', function () {
    expect($this->user->wantsEmailFor(Type::PostPublished))->toBeTrue();
    expect($this->user->wantsEmailFor(Type::PostFailed))->toBeTrue();
    expect($this->user->wantsEmailFor(Type::AccountDisconnected))->toBeTrue();
});

test('send notification respects email preferences', function () {
    Mail::fake();

    NotificationPreference::create([
        'user_id' => $this->user->id,
        'post_published' => false,
        'post_failed' => true,
        'account_disconnected' => true,
    ]);

    $post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    // Should NOT send email (post_published disabled)
    (new SendNotification(
        user: $this->user,
        workspaceId: $this->workspace->id,
        type: Type::PostPublished,
        channel: Channel::Both,
        title: 'Test',
        body: 'Test',
        mailable: new PostPublished($post),
    ))->handle();

    Mail::assertNothingQueued();

    // In-app notification should still be created
    expect(Notification::count())->toBe(1);
});

test('notification preferences update requires authentication', function () {
    $response = $this->put(route('app.notifications.preferences.update'), [
        'post_published' => true,
        'post_failed' => true,
        'account_disconnected' => true,
    ]);

    $response->assertRedirect(route('login'));
});
