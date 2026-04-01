<?php

declare(strict_types=1);

use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\ApiToken;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Owner->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    $this->user->refresh();
});

it('shows api keys page', function () {
    $token = ApiToken::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->get(route('app.api-keys.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/ApiKeys')
            ->has('apiTokens', 1)
        );
});

it('creates an api key', function () {
    $this->actingAs($this->user)
        ->post(route('app.api-keys.store'), [
            'name' => 'My API Key',
        ])
        ->assertRedirect();

    expect(ApiToken::where('workspace_id', $this->workspace->id)->count())->toBe(1);

    $token = ApiToken::where('workspace_id', $this->workspace->id)->first();
    expect($token->name)->toBe('My API Key');
    expect($token->status)->toBe('active');
});

it('creates an api key with expiration', function () {
    $this->actingAs($this->user)
        ->post(route('app.api-keys.store'), [
            'name' => 'Expiring Key',
            'expires_at' => now()->addDays(30)->format('Y-m-d'),
        ])
        ->assertRedirect();

    $token = ApiToken::where('workspace_id', $this->workspace->id)->first();
    expect($token->expires_at)->not->toBeNull();
});

it('validates name is required', function () {
    $this->actingAs($this->user)
        ->post(route('app.api-keys.store'), [])
        ->assertSessionHasErrors('name');
});

it('deletes an api key', function () {
    $token = ApiToken::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->delete(route('app.api-keys.destroy', $token))
        ->assertRedirect();

    expect(ApiToken::find($token->id))->toBeNull();
});

it('cannot delete api key from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $token = ApiToken::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $this->actingAs($this->user)
        ->delete(route('app.api-keys.destroy', $token))
        ->assertNotFound();
});

it('member cannot create api key', function () {
    $member = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $this->actingAs($member)
        ->post(route('app.api-keys.store'), ['name' => 'Test Key'])
        ->assertForbidden();
});

it('member cannot delete api key', function () {
    $member = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $token = ApiToken::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($member)
        ->delete(route('app.api-keys.destroy', $token))
        ->assertForbidden();
});

it('api keys page requires authentication', function () {
    $this->get(route('app.api-keys.index'))->assertRedirect(route('login'));
});
