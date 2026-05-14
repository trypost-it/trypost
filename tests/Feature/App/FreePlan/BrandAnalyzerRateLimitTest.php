<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;
use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    Cache::flush();
    RateLimiter::clear('brand-analyzer:127.0.0.1');
    RateLimiter::clear('brand-analyzer:10.0.0.1');

    config()->set('services.gemini.api_key', '');
    config()->set('services.openai.api_key', '');

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    Http::fake([
        '*' => Http::response('<html><head><title>Test Brand</title></head><body>Hello</body></html>', 200),
    ]);
});

test('brand analyzer is rate limited to 5 requests per IP per day', function () {
    $route = route('app.workspaces.autofill');

    for ($i = 0; $i < 5; $i++) {
        $response = $this->actingAs($this->user)
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson($route, ['url' => "https://example.com/brand{$i}"]);

        expect($response->status())->not->toBe(Response::HTTP_TOO_MANY_REQUESTS);
    }

    $response = $this->actingAs($this->user)
        ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->postJson($route, ['url' => 'https://example.com/brand6']);

    $response->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
});

test('brand analyzer caches results per URL and skips HTTP fetch on cache hit', function () {
    $url = 'https://cached.example.com';
    $cacheKey = 'brand-analyzer:'.md5($url);

    $cachedArray = [
        'name' => 'Cached Brand',
        'brand_description' => 'A cached description',
        'content_language' => 'en',
        'brand_tone' => 'professional',
        'brand_voice_notes' => null,
        'logo_url' => null,
        'brand_color' => null,
        'background_color' => null,
        'text_color' => null,
    ];

    Cache::put($cacheKey, $cachedArray, now()->addDays(7));

    // Clear any fake so we can detect if a real request is made
    Http::fake([]);

    $response = $this->actingAs($this->user)
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->postJson(route('app.workspaces.autofill'), ['url' => $url]);

    $response->assertSuccessful();
    $response->assertJson(['name' => 'Cached Brand']);

    // No HTTP requests should have been made (served from cache)
    Http::assertNothingSent();
});

test('brand analyzer stores result in cache after successful call', function () {
    $url = 'https://example.com';
    $cacheKey = 'brand-analyzer:'.md5($url);

    expect(Cache::has($cacheKey))->toBeFalse();

    $this->actingAs($this->user)
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->postJson(route('app.workspaces.autofill'), ['url' => $url])
        ->assertSuccessful();

    expect(Cache::has($cacheKey))->toBeTrue();
    expect(Cache::get($cacheKey))->toHaveKey('name');
});

test('free plan user can autofill brand without upgrade required', function () {
    config(['trypost.self_hosted' => false]);

    RateLimiter::clear('brand-analyzer:127.0.0.1');
    Cache::flush();

    $this->seed(PlanSeeder::class);
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

    // Pre-populate the cache so the action is never hit, avoiding LLM calls.
    $url = 'https://example.com/freeplan-test';
    $cached = ['name' => 'Test Brand', 'brand_tone' => 'professional', 'brand_description' => null,
        'content_language' => null, 'brand_voice_notes' => null, 'logo_url' => null,
        'brand_color' => null, 'background_color' => null, 'text_color' => null];
    Cache::put('brand-analyzer:'.md5($url), $cached, now()->addDays(7));

    $response = $this->actingAs($user)
        ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->postJson(route('app.workspaces.autofill'), ['url' => $url]);

    $response->assertStatus(200);
    $response->assertJsonPath('name', 'Test Brand');
});
