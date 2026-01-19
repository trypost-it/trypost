<?php

use App\Enums\Post\Status as PostStatus;
use App\Enums\User\Setup;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create(['setup' => Setup::Completed]);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    $this->socialAccount = SocialAccount::factory()->create(['workspace_id' => $this->workspace->id]);
});

test('store post request validates status is required', function () {
    $response = $this->actingAs($this->user)->post(route('posts.store'), [
        'platforms' => [
            [
                'social_account_id' => $this->socialAccount->id,
                'platform' => 'linkedin',
                'content' => 'Test content',
            ],
        ],
    ]);

    $response->assertSessionHasErrors('status');
});

test('store post request validates status is valid enum', function () {
    $response = $this->actingAs($this->user)->post(route('posts.store'), [
        'status' => 'invalid_status',
        'platforms' => [
            [
                'social_account_id' => $this->socialAccount->id,
                'platform' => 'linkedin',
                'content' => 'Test content',
            ],
        ],
    ]);

    $response->assertSessionHasErrors('status');
});

test('store post request validates scheduled_at is required for scheduled posts', function () {
    $response = $this->actingAs($this->user)->post(route('posts.store'), [
        'status' => PostStatus::Scheduled->value,
        'platforms' => [
            [
                'social_account_id' => $this->socialAccount->id,
                'platform' => 'linkedin',
                'content' => 'Test content',
            ],
        ],
    ]);

    $response->assertSessionHasErrors('scheduled_at');
});

test('store post request validates scheduled_at must be in future', function () {
    $response = $this->actingAs($this->user)->post(route('posts.store'), [
        'status' => PostStatus::Scheduled->value,
        'scheduled_at' => now()->subDay()->toISOString(),
        'platforms' => [
            [
                'social_account_id' => $this->socialAccount->id,
                'platform' => 'linkedin',
                'content' => 'Test content',
            ],
        ],
    ]);

    $response->assertSessionHasErrors('scheduled_at');
});

test('store post request validates platforms is required', function () {
    $response = $this->actingAs($this->user)->post(route('posts.store'), [
        'status' => PostStatus::Draft->value,
    ]);

    $response->assertSessionHasErrors('platforms');
});

test('store post request validates platforms is array', function () {
    $response = $this->actingAs($this->user)->post(route('posts.store'), [
        'status' => PostStatus::Draft->value,
        'platforms' => 'not-an-array',
    ]);

    $response->assertSessionHasErrors('platforms');
});

test('store post request validates platforms has at least one item', function () {
    $response = $this->actingAs($this->user)->post(route('posts.store'), [
        'status' => PostStatus::Draft->value,
        'platforms' => [],
    ]);

    $response->assertSessionHasErrors('platforms');
});

test('store post request validates platform social_account_id is required', function () {
    $response = $this->actingAs($this->user)->post(route('posts.store'), [
        'status' => PostStatus::Draft->value,
        'platforms' => [
            [
                'platform' => 'linkedin',
                'content' => 'Test content',
            ],
        ],
    ]);

    $response->assertSessionHasErrors('platforms.0.social_account_id');
});

test('store post request validates platform social_account_id exists', function () {
    $response = $this->actingAs($this->user)->post(route('posts.store'), [
        'status' => PostStatus::Draft->value,
        'platforms' => [
            [
                'social_account_id' => '00000000-0000-0000-0000-000000000000',
                'platform' => 'linkedin',
                'content' => 'Test content',
            ],
        ],
    ]);

    $response->assertSessionHasErrors('platforms.0.social_account_id');
});

test('store post request validates platform is required', function () {
    $response = $this->actingAs($this->user)->post(route('posts.store'), [
        'status' => PostStatus::Draft->value,
        'platforms' => [
            [
                'social_account_id' => $this->socialAccount->id,
                'content' => 'Test content',
            ],
        ],
    ]);

    $response->assertSessionHasErrors('platforms.0.platform');
});

test('store post request validates content max length', function () {
    $response = $this->actingAs($this->user)->post(route('posts.store'), [
        'status' => PostStatus::Draft->value,
        'platforms' => [
            [
                'social_account_id' => $this->socialAccount->id,
                'platform' => 'linkedin',
                'content' => str_repeat('a', 5001),
            ],
        ],
    ]);

    $response->assertSessionHasErrors('platforms.0.content');
});

test('store post request allows valid draft post', function () {
    $response = $this->actingAs($this->user)->post(route('posts.store'), [
        'status' => PostStatus::Draft->value,
        'platforms' => [
            [
                'social_account_id' => $this->socialAccount->id,
                'platform' => 'linkedin',
                'content' => 'Test content',
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();
});

test('store post request allows valid scheduled post', function () {
    $response = $this->actingAs($this->user)->post(route('posts.store'), [
        'status' => PostStatus::Scheduled->value,
        'scheduled_at' => now()->addDay()->toISOString(),
        'platforms' => [
            [
                'social_account_id' => $this->socialAccount->id,
                'platform' => 'linkedin',
                'content' => 'Test content',
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();
});
