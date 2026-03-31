<?php

declare(strict_types=1);

use App\Actions\User\CreateUser;
use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;

test('creates user with correct attributes', function () {
    $user = CreateUser::execute([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    expect($user->name)->toBe('John Doe');
    expect($user->email)->toBe('john@example.com');
    expect($user->setup)->toBe(Setup::Role);
    expect($user->email_verified_at)->toBeNull();
});

test('creates default workspace for new user', function () {
    $user = CreateUser::execute([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    expect($user->workspaces)->toHaveCount(1);
    expect($user->workspaces->first()->name)->toBe("Jane Doe's Workspace");
});

test('attaches user as workspace owner', function () {
    $user = CreateUser::execute([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $pivot = $user->workspaces->first()->pivot;
    expect($pivot->role)->toBe(Role::Owner->value);
});

test('sets current workspace on user', function () {
    $user = CreateUser::execute([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    expect($user->current_workspace_id)->toBe($user->workspaces->first()->id);
});

test('uses provided timezone for workspace', function () {
    $user = CreateUser::execute([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'timezone' => 'America/Sao_Paulo',
    ]);

    expect($user->workspaces->first()->timezone)->toBe('America/Sao_Paulo');
});

test('defaults timezone to UTC', function () {
    $user = CreateUser::execute([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    expect($user->workspaces->first()->timezone)->toBe('UTC');
});

test('invite registration sets setup to completed and verifies email', function () {
    $user = CreateUser::execute([
        'name' => 'Invited User',
        'email' => 'invited@example.com',
        'password' => 'password123',
        'is_invite' => true,
    ]);

    expect($user->setup)->toBe(Setup::Completed);
    expect($user->email_verified_at)->not->toBeNull();
});

test('creates user without password for social login', function () {
    $user = CreateUser::execute([
        'name' => 'Social User',
        'email' => 'social@example.com',
        'email_verified_at' => now(),
    ]);

    expect($user->password)->toBeNull();
    expect($user->email_verified_at)->not->toBeNull();
});
