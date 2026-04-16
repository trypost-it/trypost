<?php

declare(strict_types=1);

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
    $response = $this->get(route('app.onboarding.role'));

    $response->assertRedirect(route('login'));
});

test('step1 shows persona selection', function () {
    $response = $this->actingAs($this->user)->get(route('app.onboarding.role'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('onboarding/Role', false)
        ->has('personas')
    );
});

// Store Step 1 tests
test('store step1 requires authentication', function () {
    $response = $this->post(route('app.onboarding.role.store'), [
        'persona' => Persona::Founder->value,
    ]);

    $response->assertRedirect(route('login'));
});

test('store step1 saves persona and redirects to brand step', function () {
    $response = $this->actingAs($this->user)->post(route('app.onboarding.role.store'), [
        'persona' => Persona::Founder->value,
    ]);

    $response->assertRedirect(route('app.onboarding.brand'));

    $this->user->refresh();
    expect($this->user->persona)->toBe(Persona::Founder);
    expect($this->user->setup)->toBe(Setup::Brand);
});

test('store step1 validates persona is required', function () {
    $response = $this->actingAs($this->user)->post(route('app.onboarding.role.store'), [
        'persona' => '',
    ]);

    $response->assertSessionHasErrors('persona');
});

test('store step1 validates persona is valid enum', function () {
    $response = $this->actingAs($this->user)->post(route('app.onboarding.role.store'), [
        'persona' => 'invalid',
    ]);

    $response->assertSessionHasErrors('persona');
});

// Brand step tests
test('brand step requires authentication', function () {
    $response = $this->get(route('app.onboarding.brand'));

    $response->assertRedirect(route('login'));
});

test('brand step redirects users not at the brand step', function () {
    $this->user->update(['setup' => Setup::Role]);

    $response = $this->actingAs($this->user)->get(route('app.onboarding.brand'));

    $response->assertRedirect(route('app.onboarding.role'));
});

test('brand step shows the form with workspace defaults', function () {
    $workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'brand_tone' => 'casual',
        'content_language' => 'pt-BR',
    ]);
    $this->user->update([
        'current_workspace_id' => $workspace->id,
        'setup' => Setup::Brand,
    ]);

    $response = $this->actingAs($this->user)->get(route('app.onboarding.brand'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('onboarding/Brand', false)
        ->where('workspace.brand_tone', 'casual')
        ->where('workspace.content_language', 'pt-BR')
    );
});

test('store brand persists workspace settings and advances to connections', function () {
    $workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update([
        'current_workspace_id' => $workspace->id,
        'setup' => Setup::Brand,
    ]);

    $response = $this->actingAs($this->user)->post(route('app.onboarding.brand.store'), [
        'brand_website' => 'https://example.com',
        'brand_description' => 'We build social tools.',
        'brand_tone' => 'friendly',
        'brand_voice_notes' => 'Short and punchy.',
        'content_language' => 'pt-BR',
    ]);

    $response->assertRedirect(route('app.onboarding.account'));

    $workspace->refresh();
    expect($workspace->brand_website)->toBe('https://example.com');
    expect($workspace->brand_tone)->toBe('friendly');
    expect($workspace->content_language)->toBe('pt-BR');

    $this->user->refresh();
    expect($this->user->setup)->toBe(Setup::Connections);
});

test('store brand validates tone is in allowed list', function () {
    $workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update([
        'current_workspace_id' => $workspace->id,
        'setup' => Setup::Brand,
    ]);

    $response = $this->actingAs($this->user)->post(route('app.onboarding.brand.store'), [
        'brand_tone' => 'invalid-tone',
        'content_language' => 'en',
    ]);

    $response->assertSessionHasErrors('brand_tone');
});

test('store brand validates content_language is supported', function () {
    $workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update([
        'current_workspace_id' => $workspace->id,
        'setup' => Setup::Brand,
    ]);

    $response = $this->actingAs($this->user)->post(route('app.onboarding.brand.store'), [
        'brand_tone' => 'professional',
        'content_language' => 'fr',
    ]);

    $response->assertSessionHasErrors('content_language');
});

test('skip brand advances setup without saving workspace changes', function () {
    $workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'brand_tone' => 'casual',
    ]);
    $this->user->update([
        'current_workspace_id' => $workspace->id,
        'setup' => Setup::Brand,
    ]);

    $response = $this->actingAs($this->user)->post(route('app.onboarding.brand.skip'));

    $response->assertRedirect(route('app.onboarding.account'));

    $workspace->refresh();
    expect($workspace->brand_tone)->toBe('casual');

    $this->user->refresh();
    expect($this->user->setup)->toBe(Setup::Connections);
});

// Step 2 tests
test('step2 requires authentication', function () {
    $response = $this->get(route('app.onboarding.account'));

    $response->assertRedirect(route('login'));
});

test('step2 shows social accounts connection', function () {
    $this->user->update(['setup' => Setup::Connections]);

    $response = $this->actingAs($this->user)->get(route('app.onboarding.account'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('onboarding/Account', false)
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

    $response = $this->actingAs($this->user)->get(route('app.onboarding.account'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('hasWorkspace', true)
    );
});

// Store Step 2 tests
test('store step2 requires authentication', function () {
    $response = $this->post(route('app.onboarding.account.store'));

    $response->assertRedirect(route('login'));
});

test('store step2 completes setup in self-hosted mode', function () {
    config(['trypost.self_hosted' => true]);
    $this->user->update(['setup' => Setup::Connections]);

    $response = $this->actingAs($this->user)->post(route('app.onboarding.account.store'));

    $response->assertRedirect(route('app.calendar'));

    $this->user->refresh();
    expect($this->user->setup)->toBe(Setup::Completed);
});

// Complete tests
test('complete requires authentication', function () {
    $response = $this->get(route('app.onboarding.account'));

    $response->assertRedirect(route('login'));
});

test('completed user is redirected to calendar', function () {
    $this->user->update(['setup' => Setup::Completed]);

    $response = $this->actingAs($this->user)->get(route('app.onboarding.account'));

    $response->assertRedirect(route('app.calendar'));
});

// Step enforcement tests
test('step1 redirects to connect when user already completed role step', function () {
    $this->user->update(['setup' => Setup::Connections]);

    $response = $this->actingAs($this->user)->get(route('app.onboarding.role'));

    $response->assertRedirect(route('app.onboarding.account'));
});

test('step1 redirects to calendar when setup is completed', function () {
    $this->user->update(['setup' => Setup::Completed]);

    $response = $this->actingAs($this->user)->get(route('app.onboarding.role'));

    $response->assertRedirect(route('app.calendar'));
});

test('step2 redirects to role when user has not completed role step', function () {
    $this->user->update(['setup' => Setup::Role]);

    $response = $this->actingAs($this->user)->get(route('app.onboarding.account'));

    $response->assertRedirect(route('app.onboarding.role'));
});

test('step2 redirects to calendar when setup is completed', function () {
    $this->user->update(['setup' => Setup::Completed]);

    $response = $this->actingAs($this->user)->get(route('app.onboarding.account'));

    $response->assertRedirect(route('app.calendar'));
});

test('user on role step can access role page', function () {
    $this->user->update(['setup' => Setup::Role]);

    $response = $this->actingAs($this->user)->get(route('app.onboarding.role'));

    $response->assertOk();
});

test('user on connections step can access connect page', function () {
    $this->user->update(['setup' => Setup::Connections]);

    $response = $this->actingAs($this->user)->get(route('app.onboarding.account'));

    $response->assertOk();
});
