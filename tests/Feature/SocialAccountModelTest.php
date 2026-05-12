<?php

declare(strict_types=1);

use App\Enums\Notification\Type;
use App\Enums\SocialAccount\Status;
use App\Jobs\SendNotification;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->owner->id]);
});

test('markAsTokenExpired updates status and dispatches notification when transitioning from connected', function () {
    $account = SocialAccount::factory()->x()->create([
        'workspace_id' => $this->workspace->id,
        'status' => Status::Connected,
        'username' => 'testuser',
    ]);

    $account->markAsTokenExpired('refresh_token rejected');

    expect($account->fresh()->status)->toBe(Status::TokenExpired);
    expect($account->fresh()->error_message)->toBe('refresh_token rejected');

    Queue::assertPushed(SendNotification::class, function ($job) {
        return $job->user->id === $this->owner->id
            && $job->type === Type::AccountDisconnected
            && str_contains($job->title, 'needs to be reconnected');
    });
});

test('markAsTokenExpired does not dispatch notification when already token expired', function () {
    $account = SocialAccount::factory()->x()->create([
        'workspace_id' => $this->workspace->id,
        'status' => Status::TokenExpired,
        'disconnected_at' => now()->subDay(),
    ]);

    $account->markAsTokenExpired('another failure');

    Queue::assertNotPushed(SendNotification::class);
});

test('markAsTokenExpired does not dispatch notification when account is disconnected', function () {
    $account = SocialAccount::factory()->x()->create([
        'workspace_id' => $this->workspace->id,
        'status' => Status::Disconnected,
        'disconnected_at' => now()->subDay(),
    ]);

    $account->markAsTokenExpired('refresh_token rejected after disconnect');

    Queue::assertNotPushed(SendNotification::class);
});
