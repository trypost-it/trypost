<?php

declare(strict_types=1);

use App\Ai\Agents\SocialMediaAssistant;
use App\Ai\Tools\AttachmentCollector;
use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\AiMessage;
use App\Models\AiUsageLog;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    app(AttachmentCollector::class)->clear();
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

test('store creates user and assistant messages from agent response', function () {
    SocialMediaAssistant::fake(['Here is a great caption for your post!']);

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

test('store surfaces attachments pushed by tools into the AiMessage', function () {
    // Simulate the agent invoking generate_image — it would push to collector.
    $collector = app(AttachmentCollector::class);

    SocialMediaAssistant::fake(function () use ($collector) {
        $collector->push([
            'id' => 'test-media-id',
            'path' => 'medias/test.png',
            'url' => 'https://example.com/medias/test.png',
            'mime_type' => 'image/png',
            'type' => 'image',
        ]);

        return 'Generated a vertical image and attached it to the post.';
    });

    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.assistant.store', $this->post), [
            'body' => 'Generate an image of a sunset on the beach',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('assistant_message.attachments.0.id', 'test-media-id');
    $response->assertJsonPath('assistant_message.attachments.0.type', 'image');
    $response->assertJsonPath('assistant_message.metadata.intent', 'image');
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

test('store blocks prohibited content via IntentDetector', function () {
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
    SocialMediaAssistant::fake(['Here is your caption!']);

    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.assistant.store', $this->post), [
            'body' => 'Write a caption about my new coffee shop',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('assistant_message.content', 'Here is your caption!');
    $response->assertJsonPath('assistant_message.metadata.intent', 'text');
});

test('store handles agent exception gracefully', function () {
    SocialMediaAssistant::fake(function () {
        throw new RuntimeException('API quota exceeded');
    });

    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.assistant.store', $this->post), [
            'body' => 'Write me a caption about coffee',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('assistant_message.content', 'API quota exceeded');
    $response->assertJsonPath('assistant_message.metadata.error', true);
});

test('session state block reports current attachment count and remaining quota', function () {
    // Seed existing attachments in the thread
    AiMessage::factory()->create([
        'post_id' => $this->post->id,
        'role' => 'assistant',
        'attachments' => [
            ['id' => 'm1', 'type' => 'image'],
            ['id' => 'm2', 'type' => 'image'],
        ],
    ]);

    // Pre-existing monthly usage records
    AiUsageLog::factory()->image()->count(2)->create([
        'account_id' => $this->workspace->account_id,
        'workspace_id' => $this->workspace->id,
    ]);

    $capturedPrompt = null;
    SocialMediaAssistant::fake(function ($prompt) use (&$capturedPrompt) {
        $capturedPrompt = (string) $prompt;

        return 'ok';
    });

    $this->actingAs($this->user)
        ->postJson(route('app.posts.assistant.store', $this->post), [
            'body' => 'Write about coffee',
        ]);

    expect($capturedPrompt)
        ->toContain('Session state')
        ->toContain('Images already generated in this conversation: 2')
        ->toContain('Monthly quota remaining:');
});
