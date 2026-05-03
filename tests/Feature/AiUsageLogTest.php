<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\AiUsageLog;
use App\Models\Workspace;

test('monthly credits returns sum of credits for account this month', function () {
    $account = Account::factory()->create();
    $workspace = Workspace::factory()->create(['account_id' => $account->id]);

    AiUsageLog::factory()->text(credits: 5)->count(3)->create([
        'account_id' => $account->id,
        'workspace_id' => $workspace->id,
    ]);

    AiUsageLog::factory()->text(credits: 10)->count(2)->create([
        'account_id' => $account->id,
        'workspace_id' => $workspace->id,
    ]);

    expect(AiUsageLog::monthlyCredits($account->id))->toBe(35);
});

test('monthly credits excludes logs from other months', function () {
    $account = Account::factory()->create();
    $workspace = Workspace::factory()->create(['account_id' => $account->id]);

    AiUsageLog::factory()->text(credits: 10)->create([
        'account_id' => $account->id,
        'workspace_id' => $workspace->id,
    ]);

    AiUsageLog::factory()->text(credits: 50)->create([
        'account_id' => $account->id,
        'workspace_id' => $workspace->id,
        'created_at' => now()->subMonth(),
    ]);

    expect(AiUsageLog::monthlyCredits($account->id))->toBe(10);
});

test('monthly credits excludes logs from other accounts', function () {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $workspace = Workspace::factory()->create(['account_id' => $account->id]);
    $otherWorkspace = Workspace::factory()->create(['account_id' => $otherAccount->id]);

    AiUsageLog::factory()->text(credits: 10)->create([
        'account_id' => $account->id,
        'workspace_id' => $workspace->id,
    ]);

    AiUsageLog::factory()->text(credits: 100)->create([
        'account_id' => $otherAccount->id,
        'workspace_id' => $otherWorkspace->id,
    ]);

    expect(AiUsageLog::monthlyCredits($account->id))->toBe(10);
});
