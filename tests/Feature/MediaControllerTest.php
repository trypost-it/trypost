<?php

declare(strict_types=1);

use App\Enums\User\Setup;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->socialAccount = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
    $this->postPlatform = PostPlatform::factory()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);
});

// Store tests
test('store media requires authentication', function () {
    $response = $this->post(route('app.medias.store'), [
        'model' => 'postPlatform',
        'model_id' => $this->postPlatform->id,
        'media' => UploadedFile::fake()->image('test.jpg'),
    ]);

    $response->assertRedirect(route('login'));
});

test('store media uploads file', function () {
    $response = $this->actingAs($this->user)->post(route('app.medias.store'), [
        'model' => 'workspace',
        'model_id' => $this->workspace->id,
        'media' => UploadedFile::fake()->image('test.jpg'),
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['id', 'url', 'type', 'original_filename']);
});

test('store media validates required fields', function () {
    $response = $this->actingAs($this->user)->post(route('app.medias.store'), [
        'model' => '',
        'model_id' => '',
    ]);

    $response->assertSessionHasErrors(['model', 'model_id', 'media']);
});

// Destroy tests
test('destroy media requires authentication', function () {
    $media = Media::factory()->create([
        'mediable_id' => $this->postPlatform->id,
        'mediable_type' => 'postPlatform',
    ]);

    $response = $this->delete(route('app.medias.destroy', [$this->postPlatform->id, $media]));

    $response->assertRedirect(route('login'));
});

test('destroy media deletes the media', function () {
    $media = Media::factory()->create([
        'mediable_id' => $this->postPlatform->id,
        'mediable_type' => 'postPlatform',
    ]);

    $response = $this->actingAs($this->user)->delete(route('app.medias.destroy', [$this->postPlatform->id, $media]));

    $response->assertOk();
    $response->assertJson(['success' => true]);
    expect(Media::find($media->id))->toBeNull();
});

test('destroy media returns 403 for mismatched model', function () {
    $otherPostPlatform = PostPlatform::factory()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $media = Media::factory()->create([
        'mediable_id' => $otherPostPlatform->id,
        'mediable_type' => 'postPlatform',
    ]);

    $response = $this->actingAs($this->user)->delete(route('app.medias.destroy', [$this->postPlatform->id, $media]));

    $response->assertForbidden();
});

// Duplicate tests
test('duplicate media requires authentication', function () {
    $media = Media::factory()->create([
        'mediable_id' => $this->postPlatform->id,
        'mediable_type' => 'postPlatform',
    ]);

    $response = $this->post(route('app.medias.duplicate', $media), [
        'targets' => [],
    ]);

    $response->assertRedirect(route('login'));
});

test('duplicate media creates copies', function () {
    $media = Media::factory()->create([
        'mediable_id' => $this->workspace->id,
        'mediable_type' => 'workspace',
        'collection' => 'assets',
    ]);

    $response = $this->actingAs($this->user)->post(route('app.medias.duplicate', $media), [
        'targets' => [
            [
                'model' => 'workspace',
                'model_id' => $this->workspace->id,
                'collection' => 'assets',
            ],
        ],
    ]);

    $response->assertOk();
    $response->assertJsonCount(1);

    expect(Media::where('mediable_id', $this->workspace->id)->where('collection', 'assets')->count())->toBe(2);
});

// Reorder tests
test('reorder media updates order', function () {
    $media1 = Media::factory()->create([
        'mediable_id' => $this->postPlatform->id,
        'mediable_type' => 'postPlatform',
        'original_filename' => 'img1.jpg',
        'order' => 0,
    ]);
    $media2 = Media::factory()->create([
        'mediable_id' => $this->postPlatform->id,
        'mediable_type' => 'postPlatform',
        'original_filename' => 'img2.jpg',
        'order' => 1,
    ]);

    $response = $this->actingAs($this->user)->postJson(route('app.medias.reorder'), [
        'media' => [
            ['id' => $media1->id, 'order' => 1],
            ['id' => $media2->id, 'order' => 0],
        ],
    ]);

    $response->assertOk();

    expect($media1->refresh()->order)->toBe(1);
    expect($media2->refresh()->order)->toBe(0);
});

test('reorder media rejects media from other workspace', function () {
    $otherUser = User::factory()->create(['setup' => Setup::Completed]);
    $otherWorkspace = Workspace::factory()->create(['user_id' => $otherUser->id]);
    $otherUser->update(['current_workspace_id' => $otherWorkspace->id]);

    $otherPost = Post::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'user_id' => $otherUser->id,
    ]);

    $otherAccount = SocialAccount::factory()->create([
        'workspace_id' => $otherWorkspace->id,
    ]);

    $otherPlatform = PostPlatform::factory()->create([
        'post_id' => $otherPost->id,
        'social_account_id' => $otherAccount->id,
    ]);

    $otherMedia = Media::factory()->create([
        'mediable_id' => $otherPlatform->id,
        'mediable_type' => 'postPlatform',
        'original_filename' => 'img.jpg',
        'order' => 0,
    ]);

    $response = $this->actingAs($this->user)->postJson(route('app.medias.reorder'), [
        'media' => [
            ['id' => $otherMedia->id, 'order' => 0],
        ],
    ]);

    $response->assertForbidden();
});

test('reorder media validates required fields', function () {
    $response = $this->actingAs($this->user)->postJson(route('app.medias.reorder'), []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['media']);
});

test('reorder media validates media items have id and order', function () {
    $response = $this->actingAs($this->user)->postJson(route('app.medias.reorder'), [
        'media' => [
            ['invalid' => 'data'],
        ],
    ]);

    $response->assertUnprocessable();
});
