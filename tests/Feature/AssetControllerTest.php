<?php

declare(strict_types=1);

use App\Enums\User\Setup;
use App\Enums\UserWorkspace\Role;
use App\Models\Account;
use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;
use App\Services\UnsplashService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();

    $this->account = Account::factory()->create();
    $this->user = User::factory()->create([
        'setup' => Setup::Completed,
        'account_id' => $this->account->id,
    ]);
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

test('assets index shows assets page', function () {
    $response = $this->actingAs($this->user)->get(route('app.assets.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('assets/Index', false)
        ->has('assets')
    );
});

test('assets index requires authentication', function () {
    $response = $this->get(route('app.assets.index'));

    $response->assertRedirect(route('login'));
});

test('can upload an image asset', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

    $response = $this->actingAs($this->user)
        ->postJson(route('app.assets.store'), ['media' => $file]);

    $response->assertOk();
    $response->assertJsonStructure(['id', 'url', 'type', 'original_filename', 'size']);

    expect($this->workspace->getMedia('assets')->count())->toBe(1);

    $media = $this->workspace->getMedia('assets')->first();
    expect($media->original_filename)->toBe('photo.jpg');
    expect($media->collection)->toBe('assets');
});

test('can delete an asset', function () {
    $file = UploadedFile::fake()->image('photo.jpg');
    $media = $this->workspace->addMedia($file, 'assets');

    $response = $this->actingAs($this->user)
        ->delete(route('app.assets.destroy', $media));

    $response->assertRedirect();
    expect(Media::find($media->id))->toBeNull();
});

test('cannot delete asset from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->user->id,
    ]);

    $file = UploadedFile::fake()->image('photo.jpg');
    $media = $otherWorkspace->addMedia($file, 'assets');

    $response = $this->actingAs($this->user)
        ->delete(route('app.assets.destroy', $media));

    $response->assertForbidden();
});

test('can store asset from url', function () {
    $fakeImage = UploadedFile::fake()->image('photo.jpg', 800, 600);
    $imageContent = file_get_contents($fakeImage->getPathname());

    Http::fake([
        'images.unsplash.com/*' => Http::response(
            $imageContent,
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $unsplash = $this->mock(UnsplashService::class);
    $unsplash->shouldReceive('trackDownload')->once();

    $response = $this->actingAs($this->user)
        ->post(route('app.assets.store-from-url'), [
            'url' => 'https://images.unsplash.com/photo-test',
            'filename' => 'unsplash-test.jpg',
            'download_location' => 'https://api.unsplash.com/photos/test/download',
        ]);

    $response->assertRedirect();

    expect($this->workspace->getMedia('assets')->count())->toBe(1);
});

test('unsplash search returns results', function () {
    $this->mock(UnsplashService::class)
        ->shouldReceive('search')
        ->with('nature', 1)
        ->once()
        ->andReturn([
            'results' => [
                ['id' => 'abc', 'url_small' => 'https://example.com/small.jpg'],
            ],
            'total' => 1,
            'total_pages' => 1,
        ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('app.assets.unsplash.search', ['query' => 'nature']));

    $response->assertOk();
    $response->assertJsonPath('total', 1);
});
