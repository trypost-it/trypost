<?php

declare(strict_types=1);

use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\AiMessage;
use App\Models\AiUsageLog;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\AudioGenerationService;
use App\Services\Ai\Contracts\TextGenerationInterface;
use App\Services\Ai\ImageGenerationService;
use App\Services\Ai\VideoGenerationService;

beforeEach(function () {
    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    $this->mock(AudioGenerationService::class);
    $this->mock(VideoGenerationService::class);
});

test('index returns ai messages for a post', function () {
    AiMessage::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
        'role' => 'user',
        'content' => 'Write a caption',
    ]);

    AiMessage::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => null,
        'role' => 'assistant',
        'content' => 'Here is your caption!',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('app.posts.assistant.index', $this->post));

    $response->assertOk();
    $response->assertJsonCount(2, 'messages');
});

test('index rejects access to post from other workspace', function () {
    $otherUser = User::factory()->create(['setup' => Setup::Completed]);
    $otherWorkspace = Workspace::factory()->create(['user_id' => $otherUser->id]);
    $otherWorkspace->members()->attach($otherUser->id, ['role' => Role::Member->value]);
    $otherUser->update(['current_workspace_id' => $otherWorkspace->id]);

    $response = $this->actingAs($otherUser)
        ->getJson(route('app.posts.assistant.index', $this->post));

    $response->assertForbidden();
});

test('store creates user and assistant messages for text intent', function () {
    $this->mock(TextGenerationInterface::class)
        ->shouldReceive('generate')
        ->once()
        ->andReturn('Here is a great caption for your post!');

    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.assistant.store', $this->post), [
            'body' => 'Write me a caption about summer vibes',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('user_message.content', 'Write me a caption about summer vibes');
    $response->assertJsonPath('user_message.role', 'user');
    $response->assertJsonPath('assistant_message.content', 'Here is a great caption for your post!');
    $response->assertJsonPath('assistant_message.role', 'assistant');

    $this->assertDatabaseHas('ai_messages', [
        'post_id' => $this->post->id,
        'role' => 'user',
        'content' => 'Write me a caption about summer vibes',
    ]);

    $this->assertDatabaseHas('ai_messages', [
        'post_id' => $this->post->id,
        'role' => 'assistant',
        'content' => 'Here is a great caption for your post!',
    ]);
});

test('store creates user and assistant messages for image intent', function () {
    $this->mock(TextGenerationInterface::class)
        ->shouldReceive('generate')
        ->once()
        ->andReturn('[GENERATE_IMAGE:vertical]');

    $this->mock(ImageGenerationService::class)
        ->shouldReceive('generate')
        ->once()
        ->andReturn([
            'id' => 'test-media-id',
            'path' => 'medias/test.png',
            'url' => 'https://example.com/medias/test.png',
            'mime_type' => 'image/png',
            'type' => 'image',
        ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.assistant.store', $this->post), [
            'body' => 'Generate an image of a sunset on the beach',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('user_message.role', 'user');
    $response->assertJsonPath('assistant_message.role', 'assistant');
    $response->assertJsonPath('assistant_message.attachments.0.id', 'test-media-id');
    $response->assertJsonPath('assistant_message.attachments.0.type', 'image');
});

test('store validates body is required', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.assistant.store', $this->post), [
            'body' => '',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('body');
});

test('store rejects access to post from other workspace', function () {
    $otherUser = User::factory()->create(['setup' => Setup::Completed]);
    $otherWorkspace = Workspace::factory()->create(['user_id' => $otherUser->id]);
    $otherWorkspace->members()->attach($otherUser->id, ['role' => Role::Member->value]);
    $otherUser->update(['current_workspace_id' => $otherWorkspace->id]);

    $response = $this->actingAs($otherUser)
        ->postJson(route('app.posts.assistant.store', $this->post), [
            'body' => 'Write me a caption',
        ]);

    $response->assertForbidden();
});

test('store blocks prohibited content', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.assistant.store', $this->post), [
            'body' => 'Create porn content for my post',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('assistant_message.metadata.intent', 'blocked');
    $response->assertJsonPath('assistant_message.metadata.error', true);

    $this->assertDatabaseHas('ai_messages', [
        'post_id' => $this->post->id,
        'role' => 'user',
        'content' => 'Create porn content for my post',
    ]);

    $this->assertDatabaseHas('ai_messages', [
        'post_id' => $this->post->id,
        'role' => 'assistant',
    ]);
});

test('store blocks drug related content', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.assistant.store', $this->post), [
            'body' => 'Write about cocaine usage',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('assistant_message.metadata.intent', 'blocked');
});

test('store allows safe content through', function () {
    $this->mock(TextGenerationInterface::class)
        ->shouldReceive('generate')
        ->once()
        ->andReturn('Here is your caption!');

    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.assistant.store', $this->post), [
            'body' => 'Write a caption about my new coffee shop',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('assistant_message.content', 'Here is your caption!');
    $response->assertJsonPath('assistant_message.metadata.intent', 'text');
});

test('store enforces image generation limit', function () {
    $account = $this->workspace->account;

    $this->mock(TextGenerationInterface::class)
        ->shouldReceive('generate')
        ->once()
        ->andReturn('[GENERATE_IMAGE:vertical]');

    for ($i = 0; $i < 50; $i++) {
        AiUsageLog::factory()->image()->create([
            'account_id' => $account->id,
            'workspace_id' => $this->workspace->id,
        ]);
    }

    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.assistant.store', $this->post), [
            'body' => 'Generate an image of a sunset',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('assistant_message.metadata.limit_reached', true);
});

test('store enforces video generation limit', function () {
    $account = $this->workspace->account;

    $this->mock(TextGenerationInterface::class)
        ->shouldReceive('generate')
        ->once()
        ->andReturn('[GENERATE_VIDEO:vertical]');

    for ($i = 0; $i < 10; $i++) {
        AiUsageLog::factory()->video()->create([
            'account_id' => $account->id,
            'workspace_id' => $this->workspace->id,
        ]);
    }

    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.assistant.store', $this->post), [
            'body' => 'Create a video about coffee',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('assistant_message.metadata.limit_reached', true);
});

test('store handles service exception gracefully', function () {
    $this->mock(TextGenerationInterface::class)
        ->shouldReceive('generate')
        ->once()
        ->andThrow(new RuntimeException('API quota exceeded'));

    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.assistant.store', $this->post), [
            'body' => 'Write me a caption about coffee',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('assistant_message.content', 'API quota exceeded');
});
