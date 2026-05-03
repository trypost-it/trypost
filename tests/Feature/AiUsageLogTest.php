<?php

declare(strict_types=1);

use App\Enums\Ai\UsageType;
use App\Models\Account;
use App\Models\AiUsageLog;
use App\Models\Workspace;

test('monthly count returns correct count for account and type', function () {
    $account = Account::factory()->create();
    $workspace = Workspace::factory()->create(['account_id' => $account->id]);

    AiUsageLog::factory()->image()->count(3)->create([
        'account_id' => $account->id,
        'workspace_id' => $workspace->id,
    ]);

    AiUsageLog::factory()->text()->count(2)->create([
        'account_id' => $account->id,
        'workspace_id' => $workspace->id,
    ]);

    expect(AiUsageLog::monthlyCount($account->id, UsageType::Image))->toBe(3);
    expect(AiUsageLog::monthlyCount($account->id, UsageType::Text))->toBe(2);
});

test('monthly count excludes logs from other months', function () {
    $account = Account::factory()->create();
    $workspace = Workspace::factory()->create(['account_id' => $account->id]);

    AiUsageLog::factory()->image()->create([
        'account_id' => $account->id,
        'workspace_id' => $workspace->id,
    ]);

    AiUsageLog::factory()->image()->create([
        'account_id' => $account->id,
        'workspace_id' => $workspace->id,
        'created_at' => now()->subMonth(),
    ]);

    expect(AiUsageLog::monthlyCount($account->id, UsageType::Image))->toBe(1);
});

test('monthly count excludes logs from other accounts', function () {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $workspace = Workspace::factory()->create(['account_id' => $account->id]);
    $otherWorkspace = Workspace::factory()->create(['account_id' => $otherAccount->id]);

    AiUsageLog::factory()->image()->create([
        'account_id' => $account->id,
        'workspace_id' => $workspace->id,
    ]);

    AiUsageLog::factory()->image()->create([
        'account_id' => $otherAccount->id,
        'workspace_id' => $otherWorkspace->id,
    ]);

    expect(AiUsageLog::monthlyCount($account->id, UsageType::Image))->toBe(1);
});
