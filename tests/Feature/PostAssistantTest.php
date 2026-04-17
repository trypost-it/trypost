<?php

declare(strict_types=1);

use App\Ai\Agents\SocialMediaAssistant;
use App\Ai\Tools\AttachmentCollector;
use App\Enums\AiMessage\Status;
use App\Enums\UserWorkspace\Role;
use App\Events\Ai\AssistantMessageUpdated;
use App\Jobs\Ai\GenerateAssistantResponse;
use App\Models\AiMessage;
use App\Models\AiUsageLog;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\HumanizerService;
use App\Services\Ai\IntentDetector;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::factory()->create([]);
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
    $otherUser = User::factory()->create([]);
    $otherWorkspace = Workspace::factory()->create(['user_id' => $otherUser->id]);
    $otherWorkspace->members()->attach($otherUser->id, ['role' => Role::Member->value]);
    $otherUser->update(['current_workspace_id' => $otherWorkspace->id]);

    $response = $this->actingAs($otherUser)
        ->getJson(route('app.posts.assistant.index', $this->post));

    $response->assertForbidden();
});

test('store creates user message and pending assistant placeholder, dispatches job', function () {
    Bus::fake();

    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.assistant.store', $this->post), [
            'body' => 'Write me a caption about summer vibes',
        ]);

    $response->assertAccepted();
    $response->assertJsonPath('user_message.content', 'Write me a caption about summer vibes');
    $response->assertJsonPath('user_message.role', 'user');
    $response->assertJsonPath('assistant_message.role', 'assistant');
    $response->assertJsonPath('assistant_message.status', 'pending');
    $response->assertJsonPath('assistant_message.content', '');

    $this->assertDatabaseHas('ai_messages', [
        'post_id' => $this->post->id,
        'role' => 'user',
        'content' => 'Write me a caption about summer vibes',
    ]);

    $this->assertDatabaseHas('ai_messages', [
        'post_id' => $this->post->id,
        'role' => 'assistant',
        'status' => 'pending',
    ]);

    Bus::assertDispatched(GenerateAssistantResponse::class, function (GenerateAssistantResponse $job) {
        return $job->prompt === 'Write me a caption about summer vibes'
            && $job->assistantMessage->post_id === $this->post->id;
    });
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
    $otherUser = User::factory()->create([]);
    $otherWorkspace = Workspace::factory()->create(['user_id' => $otherUser->id]);
    $otherWorkspace->members()->attach($otherUser->id, ['role' => Role::Member->value]);
    $otherUser->update(['current_workspace_id' => $otherWorkspace->id]);

    $response = $this->actingAs($otherUser)
        ->postJson(route('app.posts.assistant.store', $this->post), [
            'body' => 'Write me a caption',
        ]);

    $response->assertForbidden();
});

test('store blocks prohibited content via IntentDetector and does not dispatch job', function () {
    Bus::fake();

    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.assistant.store', $this->post), [
            'body' => 'Create porn content for my post',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('assistant_message.metadata.intent', 'blocked');
    $response->assertJsonPath('assistant_message.metadata.error', true);
    $response->assertJsonPath('assistant_message.status', 'completed');

    Bus::assertNotDispatched(GenerateAssistantResponse::class);

    $this->assertDatabaseHas('ai_messages', [
        'post_id' => $this->post->id,
        'role' => 'user',
        'content' => 'Create porn content for my post',
    ]);
});

test('store blocks drug related content', function () {
    Bus::fake();

    $response = $this->actingAs($this->user)
        ->postJson(route('app.posts.assistant.store', $this->post), [
            'body' => 'Write about cocaine usage',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('assistant_message.metadata.intent', 'blocked');

    Bus::assertNotDispatched(GenerateAssistantResponse::class);
});

test('GenerateAssistantResponse job populates message with agent response and broadcasts', function () {
    Event::fake([AssistantMessageUpdated::class]);

    SocialMediaAssistant::fake([['message' => 'Here is a great caption!', 'quick_actions' => []]]);

    $assistantMessage = AiMessage::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => null,
        'role' => 'assistant',
        'content' => '',
        'status' => Status::Pending,
    ]);

    (new GenerateAssistantResponse(
        assistantMessage: $assistantMessage,
        prompt: 'Write a caption',
        intent: 'text',
    ))->handle(app(IntentDetector::class), app(AttachmentCollector::class), app(HumanizerService::class));

    $assistantMessage->refresh();
    expect($assistantMessage->content)->toBe('Here is a great caption!');
    expect($assistantMessage->status)->toBe(Status::Completed);
    expect($assistantMessage->metadata['intent'] ?? null)->toBe('text');

    Event::assertDispatched(AssistantMessageUpdated::class, fn ($e) => $e->message->id === $assistantMessage->id);
});

test('GenerateAssistantResponse job surfaces attachments from collector', function () {
    Event::fake([AssistantMessageUpdated::class]);

    $collector = app(AttachmentCollector::class);

    SocialMediaAssistant::fake(function () use ($collector) {
        $collector->push([
            'id' => 'test-media-id',
            'path' => 'medias/test.png',
            'url' => 'https://example.com/medias/test.png',
            'mime_type' => 'image/png',
            'type' => 'image',
        ]);

        return ['message' => 'Generated an image', 'quick_actions' => []];
    });

    $assistantMessage = AiMessage::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => null,
        'role' => 'assistant',
        'content' => '',
        'status' => Status::Pending,
    ]);

    (new GenerateAssistantResponse(
        assistantMessage: $assistantMessage,
        prompt: 'Generate an image',
        intent: 'image',
    ))->handle(app(IntentDetector::class), $collector, app(HumanizerService::class));

    $assistantMessage->refresh();
    expect($assistantMessage->attachments)->toHaveCount(1);
    expect($assistantMessage->attachments[0]['id'])->toBe('test-media-id');
    expect($assistantMessage->metadata['intent'] ?? null)->toBe('image');
});

test('GenerateAssistantResponse failed handler marks message failed and broadcasts', function () {
    Event::fake([AssistantMessageUpdated::class]);

    $assistantMessage = AiMessage::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => null,
        'role' => 'assistant',
        'content' => '',
        'status' => Status::Generating,
    ]);

    $job = new GenerateAssistantResponse(
        assistantMessage: $assistantMessage,
        prompt: 'Anything',
        intent: 'text',
    );

    $job->failed(new RuntimeException('API quota exceeded'));

    $assistantMessage->refresh();
    expect($assistantMessage->status)->toBe(Status::Failed);
    expect($assistantMessage->content)->toBe('API quota exceeded');
    expect($assistantMessage->error_message)->toBe('API quota exceeded');
    expect($assistantMessage->metadata['error'] ?? null)->toBeTrue();

    Event::assertDispatched(AssistantMessageUpdated::class);
});

test('GenerateAssistantResponse job builds prompt with session state', function () {
    Event::fake([AssistantMessageUpdated::class]);

    AiMessage::factory()->create([
        'post_id' => $this->post->id,
        'role' => 'assistant',
        'attachments' => [
            ['id' => 'm1', 'type' => 'image'],
            ['id' => 'm2', 'type' => 'image'],
        ],
    ]);

    AiUsageLog::factory()->image()->count(2)->create([
        'account_id' => $this->workspace->account_id,
        'workspace_id' => $this->workspace->id,
    ]);

    $capturedPrompt = null;
    SocialMediaAssistant::fake(function ($prompt) use (&$capturedPrompt) {
        $capturedPrompt = (string) $prompt;

        return ['message' => 'ok', 'quick_actions' => []];
    });

    $assistantMessage = AiMessage::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => null,
        'role' => 'assistant',
        'content' => '',
        'status' => Status::Pending,
    ]);

    (new GenerateAssistantResponse(
        assistantMessage: $assistantMessage,
        prompt: 'Write about coffee',
        intent: 'text',
    ))->handle(app(IntentDetector::class), app(AttachmentCollector::class), app(HumanizerService::class));

    expect($capturedPrompt)
        ->toContain('Session state')
        ->toContain('Images already generated in this conversation: 2')
        ->toContain('Monthly quota remaining:');
});

test('GenerateAssistantResponse job runs humanizer when media is generated', function () {
    Event::fake([AssistantMessageUpdated::class]);

    $collector = app(AttachmentCollector::class);

    SocialMediaAssistant::fake([function () use ($collector) {
        $collector->push(['id' => 'img-1', 'path' => 'medias/t.png', 'url' => 'https://example.com/t.png', 'mime_type' => 'image/png', 'type' => 'image']);

        return ['message' => 'Original — text with em-dash.', 'quick_actions' => []];
    }]);

    $humanizer = Mockery::mock(HumanizerService::class);
    $humanizer->shouldReceive('humanize')
        ->once()
        ->with('Original — text with em-dash.', Mockery::any())
        ->andReturn('Original, text without em-dash.');

    $assistantMessage = AiMessage::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => null,
        'role' => 'assistant',
        'content' => '',
        'status' => Status::Pending,
    ]);

    (new GenerateAssistantResponse(
        assistantMessage: $assistantMessage,
        prompt: 'Generate an image',
        intent: 'image',
    ))->handle(app(IntentDetector::class), $collector, $humanizer);

    $assistantMessage->refresh();
    expect($assistantMessage->content)->toBe('Original, text without em-dash.');
});

test('GenerateAssistantResponse job skips humanizer for conversational turns without media', function () {
    Event::fake([AssistantMessageUpdated::class]);

    SocialMediaAssistant::fake([['message' => 'Hey! What do you want to create?', 'quick_actions' => [['label' => 'Image', 'value' => 'image']]]]);

    $humanizer = Mockery::mock(HumanizerService::class);
    $humanizer->shouldNotReceive('humanize');

    $assistantMessage = AiMessage::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => null,
        'role' => 'assistant',
        'content' => '',
        'status' => Status::Pending,
    ]);

    (new GenerateAssistantResponse(
        assistantMessage: $assistantMessage,
        prompt: 'Hello',
        intent: 'text',
    ))->handle(app(IntentDetector::class), app(AttachmentCollector::class), $humanizer);

    $assistantMessage->refresh();
    expect($assistantMessage->content)->toBe('Hey! What do you want to create?');
});

test('GenerateAssistantResponse persists quick_actions in message metadata', function () {
    Event::fake([AssistantMessageUpdated::class]);

    SocialMediaAssistant::fake([[
        'message' => 'What format do you want?',
        'quick_actions' => [
            ['label' => '📷 Image', 'value' => 'image'],
            ['label' => '🎬 Video', 'value' => 'video'],
        ],
    ]]);

    $assistantMessage = AiMessage::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => null,
        'role' => 'assistant',
        'content' => '',
        'status' => Status::Pending,
    ]);

    (new GenerateAssistantResponse(
        assistantMessage: $assistantMessage,
        prompt: 'Create a post',
        intent: 'text',
    ))->handle(app(IntentDetector::class), app(AttachmentCollector::class), app(HumanizerService::class));

    $assistantMessage->refresh();
    expect($assistantMessage->content)->toBe('What format do you want?');
    expect($assistantMessage->metadata['quick_actions'] ?? null)->toBeArray()->toHaveCount(2);
    expect($assistantMessage->metadata['quick_actions'][0]['label'])->toBe('📷 Image');
    expect($assistantMessage->metadata['quick_actions'][0]['value'])->toBe('image');
});

test('GenerateAssistantResponse handles empty quick_actions gracefully', function () {
    Event::fake([AssistantMessageUpdated::class]);

    SocialMediaAssistant::fake([['message' => 'Polished caption.', 'quick_actions' => []]]);

    $assistantMessage = AiMessage::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => null,
        'role' => 'assistant',
        'content' => '',
        'status' => Status::Pending,
    ]);

    (new GenerateAssistantResponse(
        assistantMessage: $assistantMessage,
        prompt: 'Polish my caption',
        intent: 'text',
    ))->handle(app(IntentDetector::class), app(AttachmentCollector::class), app(HumanizerService::class));

    $assistantMessage->refresh();
    expect($assistantMessage->metadata['quick_actions'] ?? null)->toBe([]);
});
