<?php

declare(strict_types=1);

use App\Actions\User\CreateUser;
use App\Enums\Plan\Slug;
use App\Enums\UserWorkspace\Role;
use App\Models\Plan;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

test('new signup is assigned to the free plan', function () {
    $user = CreateUser::execute([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'password123',
        'timezone' => 'UTC',
        'registration_ip' => '127.0.0.1',
    ]);

    $freePlan = Plan::where('slug', Slug::Free)->firstOrFail();

    expect($user->account->plan_id)->toBe($freePlan->id);
});

test('free user can reach the dashboard', function () {
    config(['trypost.self_hosted' => false]);

    $user = CreateUser::execute([
        'name' => 'Alice',
        'email' => 'alice2@example.com',
        'password' => 'password123',
        'timezone' => 'UTC',
        'registration_ip' => '127.0.0.1',
    ]);

    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $response = $this->actingAs($user)->get(route('app.accounts'));

    $response->assertOk();
});

test('inertia shared props expose plan feature flags', function () {
    config(['trypost.self_hosted' => false]);

    $user = CreateUser::execute([
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'password' => 'password123',
        'timezone' => 'UTC',
        'registration_ip' => '127.0.0.1',
    ]);

    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user->fresh())
        ->get(route('app.accounts'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.plan.slug', 'free')
            ->where('features.canUseAi', false)
            ->where('features.canUseAnalytics', false)
            ->where('features.scheduledPostsLimit', 15)
            ->where('features.blockedNetworks', ['x'])
        );
});

test('subscribe page does not show free plan as billable option', function () {
    config(['trypost.self_hosted' => false]);

    $user = CreateUser::execute([
        'name' => 'Test',
        'email' => 'subscribe@test.com',
        'password' => 'password123',
        'timezone' => 'UTC',
        'registration_ip' => '127.0.0.1',
    ]);

    $response = $this->actingAs($user->fresh())->get(route('app.subscribe'));

    $response->assertInertia(function ($page) {
        $plans = collect($page->toArray()['props']['plans'] ?? []);
        $freeIncluded = $plans->contains(fn ($p) => ($p['slug'] ?? null) === 'free');
        expect($freeIncluded)->toBeFalse();
    });
});

test('shared inertia plans prop does not include free plan', function () {
    config(['trypost.self_hosted' => false]);

    $user = CreateUser::execute([
        'name' => 'Carol',
        'email' => 'carol@test.com',
        'password' => 'password123',
        'timezone' => 'UTC',
        'registration_ip' => '127.0.0.1',
    ]);

    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user->fresh())
        ->get(route('app.accounts'))
        ->assertInertia(function ($page) {
            $plans = collect($page->toArray()['props']['plans'] ?? []);
            $freeIncluded = $plans->contains(fn ($p) => ($p['slug'] ?? null) === 'free');
            expect($freeIncluded)->toBeFalse();
        });
});
