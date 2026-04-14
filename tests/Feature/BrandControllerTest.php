<?php

declare(strict_types=1);

use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Http\Middleware\App\EnsureSubscribed;
use App\Models\Brand;
use App\Models\Plan;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['trypost.self_hosted' => true]);

    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Owner->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('can list brands', function () {
    Brand::factory()->count(2)->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)->get(route('app.brands.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('brands/Index', false)
        ->has('brands.data', 2)
    );
});

test('can create brand', function () {
    $response = $this->actingAs($this->user)->post(route('app.brands.store'), [
        'name' => 'My Brand',
    ]);

    $response->assertRedirect(route('app.brands.index'));

    $this->assertDatabaseHas('brands', [
        'workspace_id' => $this->workspace->id,
        'name' => 'My Brand',
    ]);
});

test('can update brand name', function () {
    $brand = Brand::factory()->create(['workspace_id' => $this->workspace->id, 'name' => 'Old Name']);

    $response = $this->actingAs($this->user)->put(route('app.brands.update', $brand), [
        'name' => 'New Name',
    ]);

    $response->assertRedirect(route('app.brands.index'));

    $brand->refresh();
    expect($brand->name)->toBe('New Name');
});

test('can delete brand', function () {
    $brand = Brand::factory()->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)->delete(route('app.brands.destroy', $brand));

    $response->assertRedirect(route('app.brands.index'));
    expect(Brand::find($brand->id))->toBeNull();
});

test('deleting brand nullifies social account brand_id', function () {
    $brand = Brand::factory()->create(['workspace_id' => $this->workspace->id]);
    $socialAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'brand_id' => $brand->id,
    ]);

    $this->actingAs($this->user)->delete(route('app.brands.destroy', $brand));

    $socialAccount->refresh();
    expect($socialAccount->brand_id)->toBeNull();
});

test('cannot create brand beyond plan limit', function () {
    config(['trypost.self_hosted' => false]);

    $plan = Plan::query()->first() ?? Plan::factory()->create();
    $plan->update(['brand_limit' => 0]);
    $this->workspace->update(['plan_id' => $plan->id]);

    $response = $this->withoutMiddleware(EnsureSubscribed::class)
        ->actingAs($this->user)
        ->post(route('app.brands.store'), [
            'name' => 'Should Fail',
        ]);

    $response->assertForbidden();
});

test('cannot access brands from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $brand = Brand::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $response = $this->actingAs($this->user)->put(route('app.brands.update', $brand), [
        'name' => 'Hacked',
    ]);

    $response->assertForbidden();
});
