<?php

declare(strict_types=1);

use App\Ai\Agents\PostContentReviewer;
use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
});

test('endpoint requires authentication', function () {
    $this->postJson("/posts/{$this->post->id}/ai/review", ['content' => 'hi'])
        ->assertStatus(Response::HTTP_UNAUTHORIZED);
});

test('endpoint validates content required', function () {
    $this->actingAs($this->user)
        ->postJson("/posts/{$this->post->id}/ai/review", [])
        ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->assertJsonValidationErrors(['content']);
});

test('endpoint blocks cross-workspace posts', function () {
    $otherWorkspace = Workspace::factory()->create();
    $foreignPost = Post::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $this->actingAs($this->user)
        ->postJson("/posts/{$foreignPost->id}/ai/review", ['content' => 'hi'])
        ->assertStatus(Response::HTTP_FORBIDDEN);
});

test('endpoint returns suggestions array', function () {
    PostContentReviewer::fake([
        ['suggestions' => [
            ['original' => 'i was', 'suggestion' => 'I was', 'reason' => 'Capitalize "I"'],
        ]],
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/posts/{$this->post->id}/ai/review", ['content' => 'i was here'])
        ->assertOk();

    expect($response->json('suggestions'))->toBeArray()->toHaveCount(1);
    expect($response->json('suggestions.0.original'))->toBe('i was');
});
