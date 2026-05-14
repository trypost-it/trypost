<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\Plan;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Symfony\Component\HttpFoundation\Response;

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
    $this->user = $this->user->fresh();  // refresh cached account relation
});

test('free user is blocked from AI post creation', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.ai.create'), [
            'prompt' => 'Write me a post',
            'format' => 'instagram_feed',
            'image_count' => 0,
        ]);

    $response->assertStatus(Response::HTTP_PAYMENT_REQUIRED);
    $response->assertJsonPath('upgrade_required', true);
    $response->assertJsonPath('reason', 'ai_disabled');
});

test('free user cannot start X OAuth flow', function () {
    $response = $this->actingAs($this->user)
        ->getJson(route('app.social.x.connect'));

    $response->assertStatus(Response::HTTP_PAYMENT_REQUIRED);
    $response->assertJsonPath('upgrade_required', true);
    $response->assertJsonPath('reason', 'network_disabled');
});

test('free user cannot connect a 2nd social account', function () {
    SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('app.social.linkedin.connect'));

    $response->assertStatus(Response::HTTP_PAYMENT_REQUIRED);
    $response->assertJsonPath('upgrade_required', true);
    $response->assertJsonPath('reason', 'social_account_limit');
    $response->assertJsonPath('limit', 1);
    $response->assertJsonPath('current', 1);
});

test('free user is redirected from analytics page', function () {
    $response = $this->actingAs($this->user)->get(route('app.analytics'));

    $response->assertRedirect(route('app.subscribe'));
});
