<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Jobs\Ai\StreamPostCreation;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

// --- POST /posts/ai/create (start) ---

test('start requires authentication', function () {
    $this->postJson('/posts/ai/create', ['prompt' => 'hello', 'format' => 'x_post'])
        ->assertStatus(Response::HTTP_UNAUTHORIZED);
});

test('start validates prompt is required', function () {
    Bus::fake();

    $this->actingAs($this->user)
        ->postJson('/posts/ai/create', ['format' => 'x_post'])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors(['prompt']);
});

test('start validates format is required', function () {
    Bus::fake();

    $this->actingAs($this->user)
        ->postJson('/posts/ai/create', ['prompt' => 'hello'])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors(['format']);
});

test('start validates format must be a known value', function () {
    Bus::fake();

    $this->actingAs($this->user)
        ->postJson('/posts/ai/create', ['prompt' => 'hello', 'format' => 'tiktok_video'])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors(['format']);
});

test('start rejects social_account_id from another workspace', function () {
    Bus::fake();

    $otherWorkspace = Workspace::factory()->create();
    $foreignAccount = SocialAccount::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'platform' => Platform::X,
    ]);

    $this->actingAs($this->user)
        ->postJson('/posts/ai/create', [
            'prompt' => 'hello',
            'format' => 'x_post',
            'social_account_id' => $foreignAccount->id,
        ])
        ->assertStatus(Response::HTTP_FORBIDDEN);
});

test('start dispatches StreamPostCreation and returns creation_id and channel', function () {
    Bus::fake();

    $account = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::X,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/posts/ai/create', [
            'prompt' => 'Write a post about productivity',
            'format' => 'x_post',
            'social_account_id' => $account->id,
            'image_count' => 2,
        ])
        ->assertStatus(Response::HTTP_ACCEPTED);

    $creationId = $response->json('creation_id');
    expect($creationId)->toBeString()->not->toBeEmpty();
    expect($response->json('channel'))->toBe("users.{$this->user->id}.ai-creation.{$creationId}");

    Bus::assertDispatched(StreamPostCreation::class, function ($job) use ($creationId, $account) {
        return $job->userId === $this->user->id
            && $job->creationId === $creationId
            && $job->workspaceId === $this->workspace->id
            && $job->format === 'x_post'
            && $job->socialAccountId === $account->id
            && $job->imageCount === 2
            && $job->prompt === 'Write a post about productivity';
    });
});

test('start works without social_account_id', function () {
    Bus::fake();

    $this->actingAs($this->user)
        ->postJson('/posts/ai/create', [
            'prompt' => 'Write a LinkedIn post',
            'format' => 'linkedin_post',
        ])
        ->assertStatus(Response::HTTP_ACCEPTED)
        ->assertJsonStructure(['creation_id', 'channel']);

    Bus::assertDispatched(StreamPostCreation::class, fn ($job) => is_null($job->socialAccountId));
});

// --- POST /posts/ai/create/{creationId}/finalize ---

test('finalize requires authentication', function () {
    $this->postJson('/posts/ai/create/fake-id/finalize')
        ->assertStatus(Response::HTTP_UNAUTHORIZED);
});

test('finalize returns 404 if creation not found in cache', function () {
    $this->actingAs($this->user)
        ->postJson('/posts/ai/create/nonexistent-id/finalize')
        ->assertStatus(Response::HTTP_NOT_FOUND);
});

test('finalize returns 404 if creation belongs to another user', function () {
    $otherUser = User::factory()->create();
    $creationId = 'test-creation-id';

    Cache::put("ai-creation:{$creationId}", [
        'workspace_id' => $this->workspace->id,
        'user_id' => $otherUser->id,
        'format' => 'x_post',
        'social_account_id' => null,
        'image_count' => 0,
        'content' => 'Some generated content',
        'created_at' => now()->toIso8601String(),
    ], now()->addMinutes(30));

    $this->actingAs($this->user)
        ->postJson("/posts/ai/create/{$creationId}/finalize")
        ->assertStatus(Response::HTTP_NOT_FOUND);
});

test('finalize creates a post and returns post_id and redirect_url', function () {
    $creationId = 'test-creation-id';
    $content = 'AI-generated content for the post';

    Cache::put("ai-creation:{$creationId}", [
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'format' => 'x_post',
        'social_account_id' => null,
        'image_count' => 0,
        'content' => $content,
        'created_at' => now()->toIso8601String(),
    ], now()->addMinutes(30));

    $response = $this->actingAs($this->user)
        ->postJson("/posts/ai/create/{$creationId}/finalize")
        ->assertStatus(Response::HTTP_OK)
        ->assertJsonStructure(['post_id', 'redirect_url']);

    $postId = $response->json('post_id');
    $post = Post::find($postId);

    expect($post)->not->toBeNull();
    expect($post->content)->toBe($content);
    expect($post->workspace_id)->toBe($this->workspace->id);
    expect($post->user_id)->toBe($this->user->id);

    // Cache entry should be cleared
    expect(Cache::get("ai-creation:{$creationId}"))->toBeNull();

    // Redirect URL should point to the edit page
    expect($response->json('redirect_url'))->toContain("/posts/{$postId}/edit");
});
