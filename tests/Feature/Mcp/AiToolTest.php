<?php

declare(strict_types=1);

use App\Enums\Ai\UsageType;
use App\Enums\UserWorkspace\Role;
use App\Mcp\Servers\TryPostServer;
use App\Mcp\Tools\Ai\GenerateImageTool;
use App\Mcp\Tools\Ai\GenerateVideoTool;
use App\Models\Account;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\VideoGenerationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Image;

beforeEach(function () {
    Storage::fake();

    $this->account = Account::factory()->create();
    $this->user = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->user->id]);

    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_test_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);
});

test('generate-image tool creates a media in the workspace gallery', function () {
    Image::fake();

    $response = TryPostServer::actingAs($this->user)
        ->tool(GenerateImageTool::class, [
            'prompt' => 'A purple sunset over the desert',
            'orientation' => 'square',
        ]);

    $response->assertOk();

    expect($this->workspace->getMedia('assets')->count())->toBe(1);

    $media = $this->workspace->getMedia('assets')->first();
    expect($media->mime_type)->toBe('image/png');
    expect($media->collection)->toBe('assets');
});

test('generate-image tool logs ai usage', function () {
    Image::fake();

    TryPostServer::actingAs($this->user)
        ->tool(GenerateImageTool::class, [
            'prompt' => 'test',
            'orientation' => 'square',
        ]);

    expect(AiUsageLog::where('workspace_id', $this->workspace->id)
        ->where('type', UsageType::Image)
        ->count())->toBe(1);
});

test('generate-image tool requires prompt and orientation', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(GenerateImageTool::class, []);

    $response->assertHasErrors();
});

test('generate-image tool rejects invalid orientation', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(GenerateImageTool::class, [
            'prompt' => 'test',
            'orientation' => 'diagonal',
        ]);

    $response->assertHasErrors();
});

test('generate-video tool delegates to VideoGenerationService and returns Media payload', function () {
    $media = $this->workspace->media()->create([
        'group_id' => Str::uuid()->toString(),
        'collection' => 'assets',
        'type' => 'video',
        'path' => 'medias/v.mp4',
        'original_filename' => 'ai-generated.mp4',
        'mime_type' => 'video/mp4',
        'size' => 4096,
        'order' => 0,
    ]);

    $mock = $this->mock(VideoGenerationService::class);
    $mock->shouldReceive('generate')->once()->andReturn($media);

    $response = TryPostServer::actingAs($this->user)
        ->tool(GenerateVideoTool::class, [
            'prompt' => 'A cat dancing',
            'orientation' => 'vertical',
        ]);

    $response->assertOk();
    $response->assertSee($media->id);
});

test('generate-video tool rejects invalid orientation', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(GenerateVideoTool::class, [
            'prompt' => 'A cat dancing',
            'orientation' => 'square',
        ]);

    $response->assertHasErrors();
});

test('generate-video tool requires prompt', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(GenerateVideoTool::class, ['orientation' => 'vertical']);

    $response->assertHasErrors();
});
