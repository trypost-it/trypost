<?php

use App\Enums\User\Setup;
use App\Models\Media;
use App\Models\PostPlatform;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    $this->user = User::factory()->create([
        'setup' => Setup::Completed,
    ]);
    $this->user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
        'quantity' => 1,
    ]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('user can upload media to workspace', function () {
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);

    $response = $this->actingAs($this->user)->postJson(route('medias.store'), [
        'media' => $file,
        'model' => 'App\Models\Workspace',
        'model_id' => $this->workspace->id,
        'collection' => 'logo',
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure(['id', 'url', 'type', 'original_filename']);
    expect(Media::count())->toBe(1);
});

test('user can delete media', function () {
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $media = $this->workspace->addMedia($file, 'logo');

    $response = $this->actingAs($this->user)->deleteJson(route('medias.destroy', [
        'modelId' => $this->workspace->id,
        'media' => $media->id,
    ]));

    $response->assertSuccessful();
    expect(Media::find($media->id))->toBeNull();
});

test('user cannot delete media from different model', function () {
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $media = $this->workspace->addMedia($file, 'logo');

    $otherWorkspace = Workspace::factory()->create();

    $response = $this->actingAs($this->user)->deleteJson(route('medias.destroy', [
        'modelId' => $otherWorkspace->id,
        'media' => $media->id,
    ]));

    $response->assertForbidden();
});

test('user can duplicate media to another model', function () {
    $file = UploadedFile::fake()->image('image.jpg', 100, 100);
    $media = $this->workspace->addMedia($file, 'default');

    $postPlatform = PostPlatform::factory()->create([
        'social_account_id' => \App\Models\SocialAccount::factory()->linkedin()->create([
            'workspace_id' => $this->workspace->id,
        ])->id,
    ]);

    $response = $this->actingAs($this->user)->postJson(route('medias.duplicate', ['media' => $media->id]), [
        'targets' => [
            [
                'model' => 'postPlatform',
                'model_id' => $postPlatform->id,
                'collection' => 'default',
            ],
        ],
    ]);

    $response->assertSuccessful();
    expect(Media::count())->toBe(2);
});

test('media store fails with invalid model type', function () {
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);

    $response = $this->actingAs($this->user)->postJson(route('medias.store'), [
        'media' => $file,
        'model' => 'invalid',
        'model_id' => $this->workspace->id,
        'collection' => 'logo',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('model');
});
