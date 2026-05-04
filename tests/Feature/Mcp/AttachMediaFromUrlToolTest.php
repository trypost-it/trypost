<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Mcp\Servers\TryPostServer;
use App\Mcp\Tools\Post\AttachMediaFromUrlTool;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);

    Storage::fake();
});

test('attaches an image from url and creates a media row', function () {
    Http::fake([
        'example.com/photo.jpg' => Http::response(
            file_get_contents(__DIR__.'/../../fixtures/1x1.png'),
            200,
            ['Content-Type' => 'image/png'],
        ),
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(AttachMediaFromUrlTool::class, [
            'post_id' => $this->post->id,
            'urls' => ['https://example.com/photo.jpg'],
        ]);

    $response->assertOk();

    expect(Media::where('mediable_id', $this->workspace->id)->count())->toBe(1);
    expect($this->post->fresh()->media)->toHaveCount(1);
});

test('rejects url that returns non-image content type', function () {
    Http::fake([
        'example.org/payload' => Http::response('not an image', 200, ['Content-Type' => 'text/html']),
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(AttachMediaFromUrlTool::class, [
            'post_id' => $this->post->id,
            'urls' => ['https://example.org/payload'],
        ]);

    $response->assertOk();

    expect(Media::where('mediable_id', $this->workspace->id)->count())->toBe(0);
    expect($this->post->fresh()->media)->toHaveCount(0);
});

test('reports failures and successes separately', function () {
    Http::fake([
        'example.com/ok.png' => Http::response(
            file_get_contents(__DIR__.'/../../fixtures/1x1.png'),
            200,
            ['Content-Type' => 'image/png'],
        ),
        'example.com/missing.png' => Http::response(null, 404),
    ]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(AttachMediaFromUrlTool::class, [
            'post_id' => $this->post->id,
            'urls' => [
                'https://example.com/ok.png',
                'https://example.com/missing.png',
            ],
        ]);

    $response->assertOk()
        ->assertSee(['example.com/missing.png']);

    expect(Media::where('mediable_id', $this->workspace->id)->count())->toBe(1);
    expect($this->post->fresh()->media)->toHaveCount(1);
});

test('post 404 from another workspace', function () {
    $other = Workspace::factory()->create();
    $post = Post::factory()->create(['workspace_id' => $other->id, 'user_id' => $this->user->id]);

    $response = TryPostServer::actingAs($this->user)
        ->tool(AttachMediaFromUrlTool::class, [
            'post_id' => $post->id,
            'urls' => ['https://example.com/photo.jpg'],
        ]);

    $response->assertHasErrors(['Post not found.']);
});

test('rejects urls with non-http(s) schemes', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(AttachMediaFromUrlTool::class, [
            'post_id' => $this->post->id,
            'urls' => ['ftp://example.com/photo.jpg'],
        ]);

    $response->assertHasErrors();

    expect($this->post->fresh()->media)->toBeEmpty();
});

test('rejects malformed url strings', function () {
    $response = TryPostServer::actingAs($this->user)
        ->tool(AttachMediaFromUrlTool::class, [
            'post_id' => $this->post->id,
            'urls' => ['not-a-url-at-all'],
        ]);

    $response->assertHasErrors();
});

test('rejects more than 10 urls per call', function () {
    $urls = collect(range(1, 11))->map(fn ($i) => "https://example.com/photo-{$i}.jpg")->all();

    $response = TryPostServer::actingAs($this->user)
        ->tool(AttachMediaFromUrlTool::class, [
            'post_id' => $this->post->id,
            'urls' => $urls,
        ]);

    $response->assertHasErrors();
});
