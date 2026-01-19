<?php

use App\Enums\User\Setup;
use App\Models\User;
use App\Models\Workspace;

test('user with completed setup can access protected routes', function () {
    config(['trypost.self_hosted' => true]);

    $user = User::factory()->create(['setup' => Setup::Completed]);
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user)
        ->get(route('calendar'))
        ->assertOk();
});

test('user on role step is redirected to onboarding step 1', function () {
    config(['trypost.self_hosted' => true]);

    $user = User::factory()->create(['setup' => Setup::Role]);
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user)
        ->get(route('calendar'))
        ->assertRedirect(route('onboarding.step1'));
});

test('user on connections step is redirected to onboarding step 2', function () {
    config(['trypost.self_hosted' => true]);

    $user = User::factory()->create(['setup' => Setup::Connections]);
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user)
        ->get(route('calendar'))
        ->assertRedirect(route('onboarding.step2'));
});

test('user on subscription step is redirected to onboarding step 2', function () {
    config(['trypost.self_hosted' => true]);

    $user = User::factory()->create(['setup' => Setup::Subscription]);
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $workspace->members()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user)
        ->get(route('calendar'))
        ->assertRedirect(route('onboarding.step2'));
});

test('user on role step can access onboarding step 1', function () {
    $user = User::factory()->create(['setup' => Setup::Role]);

    $this->actingAs($user)
        ->get(route('onboarding.step1'))
        ->assertOk();
});

test('user on connections step can access onboarding step 2', function () {
    $user = User::factory()->create(['setup' => Setup::Connections]);

    $this->actingAs($user)
        ->get(route('onboarding.step2'))
        ->assertOk();
});

test('user on connections step can access social connect routes', function () {
    $user = User::factory()->create(['setup' => Setup::Connections]);

    $this->actingAs($user)
        ->get(route('social.linkedin.connect'))
        ->assertRedirect();
});
