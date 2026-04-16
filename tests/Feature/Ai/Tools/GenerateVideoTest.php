<?php

declare(strict_types=1);

use App\Ai\Tools\AttachmentCollector;
use App\Ai\Tools\GenerateVideo;
use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\VideoGenerationService;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
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

test('tool delegates to VideoGenerationService and pushes attachment to collector', function () {
    $expected = [
        'id' => 'video-uuid',
        'path' => 'medias/video.mp4',
        'url' => 'https://example.com/medias/video.mp4',
        'mime_type' => 'video/mp4',
        'type' => 'video',
    ];

    $mock = $this->mock(VideoGenerationService::class);
    $mock->shouldReceive('generate')->once()->andReturn($expected);

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
    expect(app(AttachmentCollector::class)->all()[0])->toBe($expected);
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
