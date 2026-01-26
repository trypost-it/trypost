<?php

use App\Enums\SocialAccount\Platform;
use App\Enums\SocialAccount\Status;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\LinkedInTokenSynchronizer;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->synchronizer = new LinkedInTokenSynchronizer;
});

test('syncs tokens from linkedin personal to linkedin page', function () {
    $linkedInUserId = 'linkedin-user-123';

    $personalAccount = SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => $linkedInUserId,
        'access_token' => 'new-access-token',
        'refresh_token' => 'new-refresh-token',
        'token_expires_at' => now()->addDays(60),
    ]);

    $pageAccount = SocialAccount::factory()->linkedinPage()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'org-123',
        'access_token' => 'old-access-token',
        'refresh_token' => 'old-refresh-token',
        'token_expires_at' => now()->subDay(),
        'meta' => [
            'organization_id' => 'org-123',
            'admin_user_id' => $linkedInUserId,
            'admin_name' => 'Test User',
        ],
    ]);

    $this->synchronizer->syncTokens($personalAccount);

    $pageAccount->refresh();
    expect($pageAccount->access_token)->toBe('new-access-token');
    expect($pageAccount->refresh_token)->toBe('new-refresh-token');
    expect($pageAccount->token_expires_at->toDateString())->toBe(now()->addDays(60)->toDateString());
});

test('syncs tokens from linkedin page to linkedin personal', function () {
    $linkedInUserId = 'linkedin-user-456';

    $personalAccount = SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => $linkedInUserId,
        'access_token' => 'old-access-token',
        'refresh_token' => 'old-refresh-token',
        'token_expires_at' => now()->subDay(),
    ]);

    $pageAccount = SocialAccount::factory()->linkedinPage()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'org-456',
        'access_token' => 'new-access-token',
        'refresh_token' => 'new-refresh-token',
        'token_expires_at' => now()->addDays(60),
        'meta' => [
            'organization_id' => 'org-456',
            'admin_user_id' => $linkedInUserId,
            'admin_name' => 'Test User',
        ],
    ]);

    $this->synchronizer->syncTokens($pageAccount);

    $personalAccount->refresh();
    expect($personalAccount->access_token)->toBe('new-access-token');
    expect($personalAccount->refresh_token)->toBe('new-refresh-token');
    expect($personalAccount->token_expires_at->toDateString())->toBe(now()->addDays(60)->toDateString());
});

test('marks disconnected account as connected after sync', function () {
    $linkedInUserId = 'linkedin-user-789';

    $personalAccount = SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => $linkedInUserId,
        'access_token' => 'new-access-token',
        'refresh_token' => 'new-refresh-token',
        'token_expires_at' => now()->addDays(60),
    ]);

    $pageAccount = SocialAccount::factory()->linkedinPage()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'org-789',
        'access_token' => 'old-access-token',
        'refresh_token' => 'old-refresh-token',
        'status' => Status::Disconnected,
        'meta' => [
            'organization_id' => 'org-789',
            'admin_user_id' => $linkedInUserId,
            'admin_name' => 'Test User',
        ],
    ]);

    $this->synchronizer->syncTokens($personalAccount);

    $pageAccount->refresh();
    expect($pageAccount->status)->toBe(Status::Connected);
});

test('does not sync when no linked account exists', function () {
    $personalAccount = SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => 'linkedin-user-solo',
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
    ]);

    // This should not throw any errors
    $this->synchronizer->syncTokens($personalAccount);

    // Just verify the account still has its original tokens
    expect($personalAccount->access_token)->toBe('access-token');
});

test('does not sync across different workspaces', function () {
    $linkedInUserId = 'linkedin-user-cross';
    $otherWorkspace = Workspace::factory()->create(['user_id' => $this->user->id]);

    $personalAccount = SocialAccount::factory()->linkedin()->create([
        'workspace_id' => $this->workspace->id,
        'platform_user_id' => $linkedInUserId,
        'access_token' => 'new-access-token',
        'refresh_token' => 'new-refresh-token',
    ]);

    $pageAccountOtherWorkspace = SocialAccount::factory()->linkedinPage()->create([
        'workspace_id' => $otherWorkspace->id,
        'platform_user_id' => 'org-other',
        'access_token' => 'old-access-token',
        'refresh_token' => 'old-refresh-token',
        'meta' => [
            'organization_id' => 'org-other',
            'admin_user_id' => $linkedInUserId,
            'admin_name' => 'Test User',
        ],
    ]);

    $this->synchronizer->syncTokens($personalAccount);

    $pageAccountOtherWorkspace->refresh();
    // Should NOT have been synced because it's in a different workspace
    expect($pageAccountOtherWorkspace->access_token)->toBe('old-access-token');
});
