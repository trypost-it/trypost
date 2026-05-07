<?php

declare(strict_types=1);

use App\Actions\User\CreateUser;
use App\Jobs\PostHog\SyncUser;
use App\Models\Account;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;

test('CreateUser creates user with account but no workspace and no current_workspace_id', function () {
    $user = CreateUser::execute([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'secret123',
    ]);

    expect($user->name)->toBe('Jane Doe');
    expect($user->email)->toBe('jane@example.com');
    expect($user->current_workspace_id)->toBeNull();
    expect($user->account_id)->not->toBeNull();
    expect(Workspace::count())->toBe(0);
    expect(Account::find($user->account_id))->not->toBeNull();
});

test('CreateUser sets account owner_id to the new user', function () {
    $user = CreateUser::execute([
        'name' => 'Jane Doe',
        'email' => 'jane2@example.com',
        'password' => 'secret123',
    ]);

    expect($user->account->owner_id)->toBe($user->id);
});

test('CreateUser invite-style still creates user without workspace (workspace assignment happens via invite acceptance)', function () {
    $user = CreateUser::execute([
        'name' => 'Invited',
        'email' => 'invited@example.com',
        'password' => 'secret123',
        'is_invite' => true,
    ]);

    expect($user->email_verified_at)->not->toBeNull();
    expect(Workspace::count())->toBe(0);
});

test('CreateUser dispatches SyncUser with the new user id when PostHog is enabled', function () {
    config(['services.posthog.enabled' => true, 'services.posthog.api_key' => 'phc_test_key']);
    Bus::fake([SyncUser::class]);

    $user = CreateUser::execute([
        'name' => 'Jane Doe',
        'email' => 'jane.posthog@example.com',
        'password' => 'secret123',
    ]);

    Bus::assertDispatched(
        SyncUser::class,
        fn ($job) => $job->userId === (string) $user->id,
    );
});

test('CreateUser does not dispatch SyncUser when PostHog is disabled', function () {
    config(['services.posthog.enabled' => false]);
    Bus::fake([SyncUser::class]);

    CreateUser::execute([
        'name' => 'Jane Doe',
        'email' => 'jane.posthog.disabled@example.com',
        'password' => 'secret123',
    ]);

    Bus::assertNotDispatched(SyncUser::class);
});
