<?php

use App\Enums\SocialAccount\Platform;
use App\Enums\User\Persona;
use App\Enums\User\Setup;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create(['setup' => Setup::Role]);
});

// Step 1 tests
test('step1 requires authentication', function () {
    $response = $this->get(route('onboarding.step1'));

    $response->assertRedirect(route('login'));
});

test('step1 shows persona selection', function () {
    $response = $this->actingAs($this->user)->get(route('onboarding.step1'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('onboarding/Step1', false)
        ->has('personas')
    );
});

// Store Step 1 tests
test('store step1 requires authentication', function () {
    $response = $this->post(route('onboarding.step1.store'), [
        'persona' => Persona::Founder->value,
    ]);

    $response->assertRedirect(route('login'));
});

test('store step1 saves persona and redirects to step2', function () {
    $response = $this->actingAs($this->user)->post(route('onboarding.step1.store'), [
        'persona' => Persona::Founder->value,
    ]);

    $response->assertRedirect(route('onboarding.step2'));

    $this->user->refresh();
    expect($this->user->persona)->toBe(Persona::Founder);
    expect($this->user->setup)->toBe(Setup::Connections);
});

test('store step1 validates persona is required', function () {
    $response = $this->actingAs($this->user)->post(route('onboarding.step1.store'), [
        'persona' => '',
    ]);

    $response->assertSessionHasErrors('persona');
});

test('store step1 validates persona is valid enum', function () {
    $response = $this->actingAs($this->user)->post(route('onboarding.step1.store'), [
        'persona' => 'invalid',
    ]);

    $response->assertSessionHasErrors('persona');
});

// Step 2 tests
test('step2 requires authentication', function () {
    $response = $this->get(route('onboarding.step2'));

    $response->assertRedirect(route('login'));
});

test('step2 shows social accounts connection', function () {
    $this->user->update(['setup' => Setup::Connections]);

    $response = $this->actingAs($this->user)->get(route('onboarding.step2'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('onboarding/Step2', false)
        ->has('platforms')
        ->has('hasWorkspace')
    );
});

test('step2 shows connected accounts for workspace', function () {
    $this->user->update(['setup' => Setup::Connections]);
    $workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $workspace->id]);

    SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::LinkedIn,
    ]);

    $response = $this->actingAs($this->user)->get(route('onboarding.step2'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('hasWorkspace', true)
    );
});

// Store Step 2 tests
test('store step2 requires authentication', function () {
    $response = $this->post(route('onboarding.step2.store'));

    $response->assertRedirect(route('login'));
});

test('store step2 completes setup in self-hosted mode', function () {
    config(['trypost.self_hosted' => true]);
    $this->user->update(['setup' => Setup::Connections]);

    $response = $this->actingAs($this->user)->post(route('onboarding.step2.store'));

    $response->assertRedirect(route('calendar'));

    $this->user->refresh();
    expect($this->user->setup)->toBe(Setup::Completed);
});

// Complete tests
test('complete requires authentication', function () {
    $response = $this->get(route('onboarding.complete'));

    $response->assertRedirect(route('login'));
});

test('complete marks setup as completed', function () {
    $this->user->update(['setup' => Setup::Subscription]);

    $response = $this->actingAs($this->user)->get(route('onboarding.complete'));

    $response->assertRedirect(route('calendar'));

    $this->user->refresh();
    expect($this->user->setup)->toBe(Setup::Completed);
});
