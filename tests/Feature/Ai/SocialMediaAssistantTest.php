<?php

declare(strict_types=1);

use App\Ai\Agents\SocialMediaAssistant;
use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\AiMessage;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;

beforeEach(function () {
    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Paulo Coffee',
        'brand_tone' => 'friendly',
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
});

test('instructions include brand context from workspace', function () {
    $agent = new SocialMediaAssistant($this->workspace, $this->post);

    $instructions = $agent->instructions();

    expect($instructions)
        ->toContain('Paulo Coffee')
        ->toContain('friendly');
});

test('messages returns AiMessage rows as SDK messages', function () {
    AiMessage::factory()->create([
        'post_id' => $this->post->id,
        'role' => 'user',
        'content' => 'Write a caption',
    ]);
    AiMessage::factory()->create([
        'post_id' => $this->post->id,
        'role' => 'assistant',
        'content' => 'Here it is!',
    ]);

    $agent = new SocialMediaAssistant($this->workspace, $this->post);

    $messages = collect($agent->messages())->all();

    expect($messages)->toHaveCount(2);
    expect($messages[0])->toBeInstanceOf(Message::class);
    expect($messages[0]->role->value)->toBe('user');
    expect($messages[0]->content)->toBe('Write a caption');
    expect($messages[1]->role->value)->toBe('assistant');
    expect($messages[1]->content)->toBe('Here it is!');
});

test('messages is empty when no post is provided', function () {
    $agent = new SocialMediaAssistant($this->workspace);

    $messages = collect($agent->messages())->all();

    expect($messages)->toBeEmpty();
});

test('messages is empty when post has no AiMessages', function () {
    $agent = new SocialMediaAssistant($this->workspace, $this->post);

    $messages = collect($agent->messages())->all();

    expect($messages)->toBeEmpty();
});

test('messages enriches assistant content with attachment summary', function () {
    AiMessage::factory()->create([
        'post_id' => $this->post->id,
        'role' => 'assistant',
        'content' => 'Here is your image',
        'attachments' => [
            ['id' => 'a', 'type' => 'image'],
            ['id' => 'b', 'type' => 'image'],
        ],
    ]);

    $agent = new SocialMediaAssistant($this->workspace, $this->post);

    $messages = collect($agent->messages())->all();

    expect($messages[0]->content)->toContain('Here is your image');
    expect($messages[0]->content)->toContain('[This assistant message attached: 2 image]');
});

test('provider honors trypost.ai.text_provider config', function () {
    config()->set('trypost.ai.text_provider', 'openai');

    expect((new SocialMediaAssistant($this->workspace))->provider())->toBe(Lab::OpenAI);

    config()->set('trypost.ai.text_provider', 'gemini');

    expect((new SocialMediaAssistant($this->workspace))->provider())->toBe(Lab::Gemini);
});
