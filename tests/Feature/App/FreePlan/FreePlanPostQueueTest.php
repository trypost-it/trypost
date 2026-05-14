<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
use App\Enums\Post\Status as PostStatus;
use App\Enums\UserWorkspace\Role;
use App\Features\ScheduledPostsLimit;
use App\Models\Account;
use App\Models\Plan;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

test('scheduled posts limit resolves to plan value for free', function () {
    $freePlan = Plan::where('slug', Slug::Free)->firstOrFail();
    $account = Account::factory()->create(['plan_id' => $freePlan->id]);

    $value = Feature::for($account)->value(ScheduledPostsLimit::class);

    expect($value)->toBe(15);
});

test('scheduled posts limit resolves to null (unlimited) for paid', function () {
    $proPlan = Plan::where('slug', Slug::Pro)->firstOrFail();
    $account = Account::factory()->create(['plan_id' => $proPlan->id]);

    $value = Feature::for($account)->value(ScheduledPostsLimit::class);

    expect($value)->toBeNull();
});

test('free user cannot schedule a 16th post', function () {
    config(['trypost.self_hosted' => false]);

    $freePlan = Plan::where('slug', Slug::Free)->firstOrFail();
    $account = Account::factory()->create(['plan_id' => $freePlan->id]);
    $user = User::factory()->create(['account_id' => $account->id]);
    $account->update(['owner_id' => $user->id]);
    $workspace = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);
    $user = $user->fresh();

    // Seed 15 already-scheduled posts in the future
    Post::factory()->count(15)->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_at' => now()->addDays(2),
    ]);

    // Create a draft post to attempt scheduling as the 16th
    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'status' => PostStatus::Draft,
    ]);

    $response = $this->actingAs($user)->putJson(route('app.posts.update', $post), [
        'status' => PostStatus::Scheduled->value,
        'content' => 'Sixteenth scheduled post',
        'scheduled_at' => now()->addDays(3)->toIso8601String(),
    ]);

    $response->assertStatus(Response::HTTP_PAYMENT_REQUIRED);
    $response->assertJsonPath('upgrade_required', true);
    $response->assertJsonPath('reason', 'scheduled_post_limit');
});

test('free user can post now even with 15 scheduled', function () {
    config(['trypost.self_hosted' => false]);

    $freePlan = Plan::where('slug', Slug::Free)->firstOrFail();
    $account = Account::factory()->create(['plan_id' => $freePlan->id]);
    $user = User::factory()->create(['account_id' => $account->id]);
    $account->update(['owner_id' => $user->id]);
    $workspace = Workspace::factory()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
    ]);
    $workspace->members()->attach($user->id, ['role' => Role::Member->value]);
    $user->update(['current_workspace_id' => $workspace->id]);
    $user = $user->fresh();

    Post::factory()->count(15)->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_at' => now()->addDays(2),
    ]);

    // Create a draft post to publish immediately
    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'status' => PostStatus::Draft,
    ]);

    // Post now — Publishing action, not Scheduled
    $response = $this->actingAs($user)->putJson(route('app.posts.update', $post), [
        'status' => PostStatus::Publishing->value,
        'content' => 'Post now',
        'scheduled_at' => null,
    ]);

    // Publishing redirects to the post show page — must NOT be blocked with 402
    $response->assertStatus(Response::HTTP_FOUND);
    expect($response->status())->not->toBe(Response::HTTP_PAYMENT_REQUIRED);
});
