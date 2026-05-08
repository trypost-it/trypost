<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Jobs\Ai\StreamPostCreation;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('start requires authentication', function () {
    $this->postJson(route('app.posts.ai.create'), ['prompt' => 'hello', 'format' => 'x_post'])
        ->assertStatus(Response::HTTP_UNAUTHORIZED);
});

test('start validates prompt is required', function () {
    Bus::fake();

    $this->actingAs($this->user)
        ->postJson(route('app.posts.ai.create'), ['format' => 'x_post'])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors(['prompt']);
});

test('start validates format is required', function () {
    Bus::fake();

    $this->actingAs($this->user)
        ->postJson(route('app.posts.ai.create'), ['prompt' => 'hello'])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors(['format']);
});

test('start validates format must be a known value', function () {
    Bus::fake();

    $this->actingAs($this->user)
        ->postJson(route('app.posts.ai.create'), ['prompt' => 'hello', 'format' => 'tiktok_video'])
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
        ->postJson(route('app.posts.ai.create'), [
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
        ->postJson(route('app.posts.ai.create'), [
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
        ->postJson(route('app.posts.ai.create'), [
            'prompt' => 'Write a LinkedIn post',
            'format' => 'linkedin_post',
        ])
        ->assertStatus(Response::HTTP_ACCEPTED)
        ->assertJsonStructure(['creation_id', 'channel']);

    Bus::assertDispatched(StreamPostCreation::class, fn ($job) => is_null($job->socialAccountId));
});

test('start dispatches the job carrying the date param when provided', function () {
    Bus::fake();

    $this->actingAs($this->user)
        ->postJson(route('app.posts.ai.create'), [
            'prompt' => 'hello',
            'format' => 'x_post',
            'date' => '2026-06-15',
        ])
        ->assertAccepted();

    Bus::assertDispatched(StreamPostCreation::class, fn ($job) => $job->date === '2026-06-15');
});

test('start rejects invalid date format', function () {
    Bus::fake();

    $this->actingAs($this->user)
        ->postJson(route('app.posts.ai.create'), [
            'prompt' => 'hello',
            'format' => 'x_post',
            'date' => 'not-a-date',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);

    Bus::assertNotDispatched(StreamPostCreation::class);
});
