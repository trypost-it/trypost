<?php

declare(strict_types=1);

use App\Actions\Workspace\CreateWorkspace;
use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\User;

test('CreateWorkspace persists name and brand fields', function () {
    $account = Account::factory()->create();
    $user = User::factory()->create(['account_id' => $account->id]);

    $workspace = CreateWorkspace::execute($user, [
        'name' => 'Acme Inc',
        'brand_website' => 'https://acme.example',
        'brand_description' => 'We sell rockets.',
        'brand_tone' => 'professional',
        'brand_voice_notes' => 'short, punchy.',
        'content_language' => 'en',
    ]);

    expect($workspace->name)->toBe('Acme Inc');
    expect($workspace->brand_website)->toBe('https://acme.example');
    expect($workspace->brand_description)->toBe('We sell rockets.');
    expect($workspace->brand_tone)->toBe('professional');
    expect($workspace->brand_voice_notes)->toBe('short, punchy.');
    expect($workspace->content_language)->toBe('en');
    expect($workspace->account_id)->toBe($account->id);
    expect($workspace->user_id)->toBe($user->id);
});

test('CreateWorkspace switches user current workspace and attaches as admin', function () {
    $account = Account::factory()->create();
    $user = User::factory()->create(['account_id' => $account->id, 'current_workspace_id' => null]);

    $workspace = CreateWorkspace::execute($user, ['name' => 'Acme']);

    $user->refresh();
    expect($user->current_workspace_id)->toBe($workspace->id);
    expect($workspace->members->contains($user))->toBeTrue();

    $member = $workspace->members()->where('user_id', $user->id)->first();
    expect($member?->pivot->role)->toBe(Role::Admin->value);
});

test('CreateWorkspace ignores unknown extra keys like logo_url', function () {
    $account = Account::factory()->create();
    $user = User::factory()->create(['account_id' => $account->id]);

    $workspace = CreateWorkspace::execute($user, [
        'name' => 'Acme',
        'logo_url' => 'https://acme.example/logo.png',
    ]);

    expect($workspace->name)->toBe('Acme');
});
