<?php

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
    $response = $this->post(route('medias.store'), [
        'model' => 'postPlatform',
        'model_id' => $this->postPlatform->id,
        'media' => UploadedFile::fake()->image('test.jpg'),
    ]);

    $response->assertRedirect(route('login'));
});

test('store media uploads file', function () {
    $response = $this->actingAs($this->user)->post(route('medias.store'), [
        'model' => 'App\Models\PostPlatform',
        'model_id' => $this->postPlatform->id,
        'media' => UploadedFile::fake()->image('test.jpg'),
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['id', 'url', 'type', 'original_filename']);
});

test('store media validates required fields', function () {
    $response = $this->actingAs($this->user)->post(route('medias.store'), [
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

    $response = $this->delete(route('medias.destroy', [$this->postPlatform->id, $media]));

    $response->assertRedirect(route('login'));
});

test('destroy media deletes the media', function () {
    $media = Media::factory()->create([
        'mediable_id' => $this->postPlatform->id,
        'mediable_type' => 'postPlatform',
    ]);

    $response = $this->actingAs($this->user)->delete(route('medias.destroy', [$this->postPlatform->id, $media]));

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

    $response = $this->actingAs($this->user)->delete(route('medias.destroy', [$this->postPlatform->id, $media]));

    $response->assertForbidden();
});

// Duplicate tests
test('duplicate media requires authentication', function () {
    $media = Media::factory()->create([
        'mediable_id' => $this->postPlatform->id,
        'mediable_type' => 'postPlatform',
    ]);

    $response = $this->post(route('medias.duplicate', $media), [
        'targets' => [],
    ]);

    $response->assertRedirect(route('login'));
});

test('duplicate media creates copies', function () {
    $media = Media::factory()->create([
        'mediable_id' => $this->postPlatform->id,
        'mediable_type' => 'postPlatform',
    ]);

    $otherPostPlatform = PostPlatform::factory()->create([
        'post_id' => $this->post->id,
        'social_account_id' => $this->socialAccount->id,
    ]);

    $response = $this->actingAs($this->user)->post(route('medias.duplicate', $media), [
        'targets' => [
            [
                'model' => 'postPlatform',
                'model_id' => $otherPostPlatform->id,
            ],
        ],
    ]);

    $response->assertOk();
    $response->assertJsonCount(1);

    expect(Media::where('mediable_id', $otherPostPlatform->id)->count())->toBe(1);
});
