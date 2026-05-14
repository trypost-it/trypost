<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
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
    $this->user = $this->user->fresh();
});

test('free user can apply a text-only template (no AI used)', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('app.post-templates.apply', ['slug' => 'bluesky_now_live']), []);

    // Text-only templates have no slides and never invoke image generation,
    // so the AI gate must not fire for them.
    $response->assertSuccessful();
    $response->assertJsonStructure(['post_id', 'redirect_url']);
});

test('free user is blocked from applying an image-generating template', function () {
    $socialAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => 'instagram',
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(
            route('app.post-templates.apply', ['slug' => 'feature_launch_carousel']),
            ['social_account_id' => $socialAccount->id],
        );

    $response->assertStatus(Response::HTTP_PAYMENT_REQUIRED);
    $response->assertJsonPath('upgrade_required', true);
    $response->assertJsonPath('reason', 'ai_disabled');
});
