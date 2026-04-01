<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Jobs\RefreshSocialToken;
use App\Models\SocialAccount;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

test('it dispatches refresh jobs for tokens expiring within 2 hours', function () {
    Queue::fake();

    $workspace = Workspace::factory()->create();

    // Should be refreshed (expires in 1 hour)
    $expiringSoon = SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::LinkedIn,
        'status' => Status::Connected,
        'token_expires_at' => now()->addHour(),
    ]);

    // Should NOT be refreshed (expires in 5 hours)
    SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::Instagram,
        'status' => Status::Connected,
        'token_expires_at' => now()->addHours(5),
    ]);

    // Should NOT be refreshed (already expired)
    SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::TikTok,
        'status' => Status::Connected,
        'token_expires_at' => now()->subHour(),
    ]);

    // Should NOT be refreshed (disconnected)
    SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::X,
        'status' => Status::Disconnected,
        'token_expires_at' => now()->addHour(),
    ]);

    $this->artisan('social:refresh-expiring-tokens')
        ->assertSuccessful();

    Queue::assertPushed(RefreshSocialToken::class, 1);
    Queue::assertPushed(RefreshSocialToken::class, fn ($job) => $job->account->id === $expiringSoon->id);
});

test('it dispatches nothing when no tokens are expiring', function () {
    Queue::fake();

    $this->artisan('social:refresh-expiring-tokens')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});
