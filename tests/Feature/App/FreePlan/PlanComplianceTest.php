<?php

declare(strict_types=1);

use App\Actions\Plan\DetectPlanViolations;
use App\Enums\Plan\Slug;
use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\Plan;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);
    $this->seed(PlanSeeder::class);

    $freePlan = Plan::where('slug', Slug::Free)->firstOrFail();
    $this->account = Account::factory()->create(['plan_id' => $freePlan->id]);
    $this->user = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->user->id]);
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    $this->user = $this->user->fresh();
});

test('free user with 2 social accounts is redirected to compliance', function () {
    SocialAccount::factory()->count(2)->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)->get(route('app.calendar'));

    $response->assertRedirect(route('app.compliance.index'));
});

test('free user within limits passes through', function () {
    SocialAccount::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)->get(route('app.calendar'));

    $response->assertOk();
});

test('compliance page lists violations', function () {
    SocialAccount::factory()->count(3)->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)->get(route('app.compliance.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('compliance/Index')
        ->has('violations', 1)
        ->where('violations.0.dimension', 'social_accounts')
        ->where('violations.0.current', 3)
        ->where('violations.0.limit', 1)
    );
});

test('cleanup route (accounts page) is exempt from compliance check', function () {
    SocialAccount::factory()->count(3)->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)->get(route('app.accounts'));

    $response->assertOk();
});

test('upgrade route is exempt from compliance check', function () {
    SocialAccount::factory()->count(3)->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)->get(route('app.subscribe'));

    $response->assertOk();
});

test('posts index is exempt from compliance check', function () {
    SocialAccount::factory()->count(3)->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)->get(route('app.posts.index'));

    $response->assertOk();
});

test('workspaces index is exempt from compliance check', function () {
    SocialAccount::factory()->count(3)->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)->get(route('app.workspaces.index'));

    $response->assertOk();
});

test('api returns 402 with violations when json request hits blocked route', function () {
    SocialAccount::factory()->count(3)->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)->getJson(route('app.calendar'));

    $response->assertStatus(402);
    $response->assertJsonPath('upgrade_required', true);
    $response->assertJsonPath('reason', 'plan_compliance_required');
    $response->assertJsonStructure(['violations' => [['dimension', 'current', 'limit']]]);
});

test('detect plan violations returns no violations when within limits', function () {
    SocialAccount::factory()->create(['workspace_id' => $this->workspace->id]);

    $violations = DetectPlanViolations::execute($this->account);

    expect($violations)->toBeEmpty();
});

test('detect plan violations returns social accounts violation', function () {
    SocialAccount::factory()->count(2)->create(['workspace_id' => $this->workspace->id]);

    $violations = DetectPlanViolations::execute($this->account);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]['dimension'])->toBe('social_accounts')
        ->and($violations[0]['current'])->toBe(2)
        ->and($violations[0]['limit'])->toBe(1);
});
