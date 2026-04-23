<?php

declare(strict_types=1);

use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\TikTokCreatorInfo;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->account = SocialAccount::factory()->tiktok()->create([
        'workspace_id' => $this->workspace->id,
        'token_expires_at' => now()->addDays(1),
    ]);

    $this->service = new TikTokCreatorInfo;
});

test('it returns full creator payload from api response', function () {
    Http::fake([
        'https://open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response([
            'data' => [
                'creator_nickname' => 'Paulo',
                'creator_username' => 'paulocastellano',
                'creator_avatar_url' => 'https://cdn.tiktok.com/avatar.jpg',
                'privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'SELF_ONLY'],
                'comment_disabled' => false,
                'duet_disabled' => true,
                'stitch_disabled' => true,
                'max_video_post_duration_sec' => 600,
            ],
        ], 200),
    ]);

    $info = $this->service->fetch($this->account);

    expect($info['creator_nickname'])->toBe('Paulo')
        ->and($info['creator_username'])->toBe('paulocastellano')
        ->and($info['creator_avatar_url'])->toBe('https://cdn.tiktok.com/avatar.jpg')
        ->and($info['privacy_level_options'])->toBe(['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'SELF_ONLY'])
        ->and($info['comment_disabled'])->toBeFalse()
        ->and($info['duet_disabled'])->toBeTrue()
        ->and($info['stitch_disabled'])->toBeTrue()
        ->and($info['max_video_post_duration_sec'])->toBe(600);
});

test('it returns an empty payload when the api fails', function () {
    Http::fake([
        'https://open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    $info = $this->service->fetch($this->account);

    expect($info['creator_nickname'])->toBeNull()
        ->and($info['privacy_level_options'])->toBe([])
        ->and($info['comment_disabled'])->toBeFalse()
        ->and($info['duet_disabled'])->toBeFalse()
        ->and($info['stitch_disabled'])->toBeFalse()
        ->and($info['max_video_post_duration_sec'])->toBeNull();
});

test('it refreshes the token before calling when expired', function () {
    $this->account->update(['token_expires_at' => now()->subMinute()]);

    Http::fake([
        'https://open.tiktokapis.com/v2/oauth/token/' => Http::response([
            'access_token' => 'new-token',
            'refresh_token' => 'new-refresh',
            'expires_in' => 3600,
        ], 200),
        'https://open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response([
            'data' => [
                'privacy_level_options' => ['PUBLIC_TO_EVERYONE'],
            ],
        ], 200),
    ]);

    $this->service->fetch($this->account);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/oauth/token/'));
    expect($this->account->fresh()->access_token)->toBe('new-token');
});
