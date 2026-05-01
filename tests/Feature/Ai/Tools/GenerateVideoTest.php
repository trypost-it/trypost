<?php

declare(strict_types=1);

use App\Ai\Tools\AttachmentCollector;
use App\Ai\Tools\GenerateVideo;
use App\Enums\UserWorkspace\Role;
use App\Models\AiUsageLog;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\VideoGenerationService;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request as ToolRequest;

beforeEach(function () {
    $this->user = User::factory()->create([]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
    app(AttachmentCollector::class)->clear();
});

test('tool delegates to VideoGenerationService and pushes attachment to collector', function () {
    $media = $this->workspace->media()->create([
        'group_id' => Str::uuid()->toString(),
        'collection' => 'assets',
        'type' => 'video',
        'path' => 'medias/video.mp4',
        'original_filename' => 'ai-generated.mp4',
        'mime_type' => 'video/mp4',
        'size' => 1024,
        'order' => 0,
    ]);

    $mock = $this->mock(VideoGenerationService::class);
    $mock->shouldReceive('generate')->once()->andReturn($media);

    $tool = new GenerateVideo(
        workspace: $this->workspace,
        post: $this->post,
        userId: $this->user->id,
    );

    $summary = $tool->handle(new ToolRequest([
        'prompt' => 'A cat dancing',
        'orientation' => 'vertical',
    ]));

    expect((string) $summary)->toContain('video');
    expect(app(AttachmentCollector::class)->all())->toHaveCount(1);
    expect(app(AttachmentCollector::class)->all()[0]['id'])->toBe($media->id);
    expect(app(AttachmentCollector::class)->all()[0]['type'])->toBe('video');
});

test('tool refuses to generate when monthly video quota is exhausted', function () {
    // Fill up the video quota. Default (no plan attached) is 10 videos.
    AiUsageLog::factory()->video()->count(10)->create([
        'account_id' => $this->workspace->account_id,
        'workspace_id' => $this->workspace->id,
    ]);

    // VideoGenerationService should not be called when quota is hit.
    $mock = $this->mock(VideoGenerationService::class);
    $mock->shouldNotReceive('generate');

    $tool = new GenerateVideo(
        workspace: $this->workspace,
        post: $this->post,
        userId: $this->user->id,
    );

    $summary = $tool->handle(new ToolRequest([
        'prompt' => 'A cat',
        'orientation' => 'vertical',
    ]));

    expect((string) $summary)->toContain('quota exhausted');
    expect(app(AttachmentCollector::class)->all())->toBeEmpty();
});

test('tool exposes schema with prompt and orientation parameters', function () {
    $tool = new GenerateVideo(
        workspace: $this->workspace,
        post: $this->post,
        userId: $this->user->id,
    );

    $schema = $tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKeys(['prompt', 'orientation']);
});
