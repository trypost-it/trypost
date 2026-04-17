<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create([]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->workspaces()->attach($this->workspace->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('search returns matching posts by content', function () {
    $account = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id]);

    $matchingPost = Post::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id, 'content' => 'Hello marketing world']);
    PostPlatform::factory()->create([
        'post_id' => $matchingPost->id,
        'social_account_id' => $account->id,
        'enabled' => true,
    ]);

    $nonMatchingPost = Post::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id, 'content' => 'Something else entirely']);
    PostPlatform::factory()->create([
        'post_id' => $nonMatchingPost->id,
        'social_account_id' => $account->id,
        'enabled' => true,
    ]);

    $response = $this->actingAs($this->user)->get(route('app.posts.index', ['search' => 'marketing']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('posts.data', 1)
        ->where('filters.search', 'marketing')
    );
});

test('search with no matches returns empty', function () {
    $account = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id]);

    $post = Post::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id, 'content' => 'Hello world']);
    PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'enabled' => true,
    ]);

    $response = $this->actingAs($this->user)->get(route('app.posts.index', ['search' => 'nonexistent']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('posts.data', 0)
        ->where('filters.search', 'nonexistent')
    );
});

test('empty search returns all posts', function () {
    $account = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id]);

    Post::factory()->count(3)->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id])->each(function ($post) use ($account) {
        PostPlatform::factory()->create([
            'post_id' => $post->id,
            'social_account_id' => $account->id,
            'enabled' => true,
        ]);
    });

    $response = $this->actingAs($this->user)->get(route('app.posts.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('posts.data', 3)
        ->where('filters.search', '')
    );
});

test('search is case insensitive', function () {
    $account = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id]);

    $post = Post::factory()->create(['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id, 'content' => 'MARKETING CAMPAIGN']);
    PostPlatform::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'enabled' => true,
    ]);

    $response = $this->actingAs($this->user)->get(route('app.posts.index', ['search' => 'marketing']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('posts.data', 1)
    );
});
