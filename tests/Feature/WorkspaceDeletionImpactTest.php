<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\Plan;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceLabel;
use App\Models\WorkspaceSignature;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);
    $this->seed(PlanSeeder::class);

    $plan = Plan::where('slug', Slug::Pro)->firstOrFail();
    $this->account = Account::factory()->create(['plan_id' => $plan->id]);
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

test('deletion impact returns zeros for empty workspace', function () {
    $response = $this->actingAs($this->user)
        ->getJson(route('app.workspaces.deletion-impact', $this->workspace->id));

    $response->assertOk();
    $response->assertJson([
        'posts' => 0,
        'social_accounts' => 0,
        'labels' => 0,
        'signatures' => 0,
        'members' => 1,
    ]);
});

test('deletion impact returns correct counts', function () {
    Post::factory()->count(3)->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id]);
    SocialAccount::factory()->count(2)->create(['workspace_id' => $this->workspace->id]);
    WorkspaceLabel::factory()->count(4)->create(['workspace_id' => $this->workspace->id]);
    WorkspaceSignature::factory()->count(1)->create(['workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($this->user)
        ->getJson(route('app.workspaces.deletion-impact', $this->workspace->id));

    $response->assertOk();
    $response->assertJson([
        'posts' => 3,
        'social_accounts' => 2,
        'labels' => 4,
        'signatures' => 1,
        'members' => 1,
    ]);
});

test('non-owner cannot access deletion impact', function () {
    $otherUser = User::factory()->create(['account_id' => $this->account->id]);
    $this->workspace->members()->attach($otherUser->id, ['role' => Role::Member->value]);
    $otherUser->update(['current_workspace_id' => $this->workspace->id]);

    $response = $this->actingAs($otherUser->fresh())
        ->getJson(route('app.workspaces.deletion-impact', $this->workspace->id));

    $response->assertForbidden();
});

test('requires authentication for deletion impact', function () {
    $response = $this->getJson(route('app.workspaces.deletion-impact', $this->workspace->id));

    $response->assertUnauthorized();
});
