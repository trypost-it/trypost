<?php

declare(strict_types=1);

use App\Ai\Tools\AttachmentCollector;
use App\Ai\Tools\GenerateImage;
use App\Enums\Ai\UsageType;
use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Image;
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

test('tool generates image, pushes attachment to collector, creates usage log', function () {
    Image::fake();

    $tool = new GenerateImage(
        workspace: $this->workspace,
        post: $this->post,
        userId: $this->user->id,
    );

    $summary = $tool->handle(new ToolRequest([
        'prompt' => 'A sunset beach',
        'orientation' => 'vertical',
    ]));

    expect((string) $summary)->toContain('image');

    $collector = app(AttachmentCollector::class);
    expect($collector->all())->toHaveCount(1);

    $attachment = $collector->all()[0];
    expect($attachment)->toHaveKeys(['id', 'path', 'url', 'mime_type', 'type']);
    expect($attachment['type'])->toBe('image');
    expect($attachment['mime_type'])->toBe('image/png');

    Image::assertGenerated(fn ($prompt) => true);

    $this->assertDatabaseHas('workspace_ai_usages', [
        'workspace_id' => $this->workspace->id,
        'type' => UsageType::Image->value,
        'provider' => 'gemini',
    ]);
});

test('tool defaults to vertical when orientation is unknown', function () {
    Image::fake();

    $tool = new GenerateImage(
        workspace: $this->workspace,
        post: $this->post,
        userId: $this->user->id,
    );

    $tool->handle(new ToolRequest([
        'prompt' => 'A forest',
        'orientation' => 'gibberish',
    ]));

    expect(app(AttachmentCollector::class)->all()[0]['type'])->toBe('image');
});

test('tool exposes schema with prompt and orientation parameters', function () {
    $tool = new GenerateImage(
        workspace: $this->workspace,
        post: $this->post,
        userId: $this->user->id,
    );

    $schema = $tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKeys(['prompt', 'orientation']);
});
