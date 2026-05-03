<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\PostTemplate;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake();

    $this->account = Account::factory()->create();
    $this->user = User::factory()->create([
        'account_id' => $this->account->id,
    ]);
    $this->account->update(['owner_id' => $this->user->id]);
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);
});

test('index returns global templates', function () {
    PostTemplate::factory()->count(3)->create();

    $this->actingAs($this->user)
        ->getJson(route('app.post-templates.index'))
        ->assertOk()
        ->assertJsonCount(3, 'templates');
});

test('index requires authentication', function () {
    $this->getJson(route('app.post-templates.index'))
        ->assertUnauthorized();
});

test('index filters by platform', function () {
    PostTemplate::factory()->create(['platform' => 'instagram_carousel']);
    PostTemplate::factory()->create(['platform' => 'linkedin_post']);

    $this->actingAs($this->user)
        ->getJson(route('app.post-templates.index', ['platform' => 'instagram_carousel']))
        ->assertOk()
        ->assertJsonCount(1, 'templates');
});

test('index filters by category', function () {
    PostTemplate::factory()->create(['category' => 'product_launch']);
    PostTemplate::factory()->create(['category' => 'educational']);
    PostTemplate::factory()->create(['category' => 'educational']);

    $this->actingAs($this->user)
        ->getJson(route('app.post-templates.index', ['category' => 'educational']))
        ->assertOk()
        ->assertJsonCount(2, 'templates');
});

test('apply creates post and returns post_id and redirect_url', function () {
    Http::fake(['api.unsplash.com/*' => Http::response(['results' => []])]);

    $template = PostTemplate::factory()->create([
        'content' => 'Hello {{brand_name}}',
        'slides' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('app.post-templates.apply', $template))
        ->assertOk();

    expect($response->json('post_id'))->toBeString();
    expect($response->json('redirect_url'))->toContain('edit');
});

test('apply interpolates brand_name in content', function () {
    Http::fake(['api.unsplash.com/*' => Http::response(['results' => []])]);

    $this->workspace->update(['name' => 'Acme Corp']);

    $template = PostTemplate::factory()->create([
        'content' => 'Welcome to {{brand_name}}!',
        'slides' => null,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('app.post-templates.apply', $template))
        ->assertOk();

    $this->assertDatabaseHas('posts', [
        'workspace_id' => $this->workspace->id,
        'content' => 'Welcome to Acme Corp!',
    ]);
});

test('apply rejects cross-workspace social_account_id', function () {
    $otherAccount = Account::factory()->create();
    $otherUser = User::factory()->create(['account_id' => $otherAccount->id]);
    $otherAccount->update(['owner_id' => $otherUser->id]);
    $otherWorkspace = Workspace::factory()->create([
        'account_id' => $otherAccount->id,
        'user_id' => $otherUser->id,
    ]);

    $foreignSocialAccount = SocialAccount::factory()->create([
        'workspace_id' => $otherWorkspace->id,
    ]);

    $template = PostTemplate::factory()->create(['slides' => null]);

    $this->actingAs($this->user)
        ->postJson(route('app.post-templates.apply', $template), [
            'social_account_id' => $foreignSocialAccount->id,
        ])
        ->assertForbidden();
});

test('apply requires authentication', function () {
    $template = PostTemplate::factory()->create();

    $this->postJson(route('app.post-templates.apply', $template))
        ->assertUnauthorized();
});

test('apply with slides creates post even when image rendering fails', function () {
    // Unsplash returns no results → generator returns null → no media attached, post still created.
    Http::fake(['api.unsplash.com/*' => Http::response(['results' => []])]);

    $socialAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $template = PostTemplate::factory()->create([
        'content' => 'Test post',
        'slides' => [
            ['title' => 'Slide 1', 'body' => 'Body 1', 'image_keywords' => ['test']],
        ],
        'image_count' => 1,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('app.post-templates.apply', $template), [
            'social_account_id' => $socialAccount->id,
        ])
        ->assertOk();

    expect($response->json('post_id'))->toBeString();
    expect($response->json('redirect_url'))->toContain('edit');
});
