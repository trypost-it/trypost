<?php

declare(strict_types=1);

use App\Ai\Tools\AttachmentCollector;
use App\Ai\Tools\GenerateAudio;
use App\Enums\Ai\UsageType;
use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Audio;
use Laravel\Ai\Tools\Request as ToolRequest;

beforeEach(function () {
    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
    app(AttachmentCollector::class)->clear();
});

test('tool generates audio, pushes attachment to collector, creates usage log', function () {
    Audio::fake();

    $tool = new GenerateAudio(
        workspace: $this->workspace,
        post: $this->post,
        userId: $this->user->id,
    );

    $summary = $tool->handle(new ToolRequest([
        'text' => 'Welcome to my channel',
    ]));

    expect((string) $summary)->toContain('audio');
    expect(app(AttachmentCollector::class)->all())->toHaveCount(1);
    expect(app(AttachmentCollector::class)->all()[0]['type'])->toBe('audio');
    expect(app(AttachmentCollector::class)->all()[0]['mime_type'])->toBe('audio/mpeg');

    Audio::assertGenerated(fn ($prompt) => true);

    $this->assertDatabaseHas('workspace_ai_usages', [
        'workspace_id' => $this->workspace->id,
        'type' => UsageType::Audio->value,
        'provider' => 'elevenlabs',
    ]);
});

test('tool exposes schema with text parameter', function () {
    $tool = new GenerateAudio(
        workspace: $this->workspace,
        post: $this->post,
        userId: $this->user->id,
    );

    $schema = $tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('text');
});
