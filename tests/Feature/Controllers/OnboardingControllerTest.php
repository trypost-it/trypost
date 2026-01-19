<?php

use App\Enums\User\Persona;
use App\Enums\User\Setup;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create([
        'setup' => Setup::Role,
    ]);
});

test('step1 shows persona selection', function () {
    $response = $this->actingAs($this->user)->get(route('onboarding.step1'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('onboarding/Step1')
        ->has('personas')
    );
});

test('step1 can be stored with valid persona', function () {
    $response = $this->actingAs($this->user)->post(route('onboarding.step1.store'), [
        'persona' => Persona::Creator->value,
    ]);

    $response->assertRedirect(route('onboarding.step2'));
    expect($this->user->fresh()->persona)->toBe(Persona::Creator);
    expect($this->user->fresh()->setup)->toBe(Setup::Connections);
});

test('step1 fails with invalid persona', function () {
    $response = $this->actingAs($this->user)->post(route('onboarding.step1.store'), [
        'persona' => 'invalid',
    ]);

    $response->assertSessionHasErrors('persona');
});

test('step2 shows platforms page', function () {
    $this->user->update(['setup' => Setup::Connections]);
    $workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $workspace->id]);

    $response = $this->actingAs($this->user)->get(route('onboarding.step2'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('onboarding/Step2')
        ->has('platforms')
        ->has('hasWorkspace')
    );
});

test('step2 shows without workspace', function () {
    $this->user->update(['setup' => Setup::Connections]);

    $response = $this->actingAs($this->user)->get(route('onboarding.step2'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('hasWorkspace', false)
    );
});

test('step2 store redirects to calendar in self hosted mode', function () {
    config(['trypost.self_hosted' => true]);
    $this->user->update(['setup' => Setup::Connections]);

    $response = $this->actingAs($this->user)->post(route('onboarding.step2.store'));

    $response->assertRedirect(route('calendar'));
    expect($this->user->fresh()->setup)->toBe(Setup::Completed);
});

test('complete sets setup to completed', function () {
    $this->user->update(['setup' => Setup::Subscription]);

    $response = $this->actingAs($this->user)->get(route('onboarding.complete'));

    $response->assertRedirect(route('calendar'));
    expect($this->user->fresh()->setup)->toBe(Setup::Completed);
});
