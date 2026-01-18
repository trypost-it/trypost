<?php

use App\Enums\SocialAccount\Platform;
use App\Enums\User\Setup;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

// Index tests
test('accounts index requires authentication', function () {
    $response = $this->get(route('accounts'));

    $response->assertRedirect(route('login'));
});

test('accounts index shows platforms and connected accounts', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
    ]);

    $response = $this->actingAs($this->user)->get(route('accounts'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('accounts/Index', false)
        ->has('workspace')
        ->has('platforms')
    );
});

test('accounts index redirects if no workspace', function () {
    $this->user->update(['current_workspace_id' => null]);

    $response = $this->actingAs($this->user)->get(route('accounts'));

    $response->assertRedirect(route('workspaces.create'));
});

// Disconnect tests
test('disconnect requires authentication', function () {
    $account = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = $this->delete(route('accounts.disconnect', $account));

    $response->assertRedirect(route('login'));
});

test('disconnect removes social account', function () {
    $account = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)->delete(route('accounts.disconnect', $account));

    $response->assertRedirect();
    expect(SocialAccount::find($account->id))->toBeNull();
});

test('disconnect returns 403 for other workspace account', function () {
    $otherWorkspace = Workspace::factory()->create();
    $account = SocialAccount::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $response = $this->actingAs($this->user)->delete(route('accounts.disconnect', $account));

    $response->assertForbidden();
});
