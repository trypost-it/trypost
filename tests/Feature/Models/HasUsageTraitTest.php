<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Invite;
use App\Models\Plan;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->account = Account::factory()->create();
    $this->owner = User::factory()->create(['account_id' => $this->account->id]);
    $this->account->update(['owner_id' => $this->owner->id]);
});

test('usage returns correct counts across the account', function () {
    Workspace::factory()->count(2)->create([
        'account_id' => $this->account->id,
        'user_id' => $this->owner->id,
    ]);
    $workspace = $this->account->workspaces()->first();
    SocialAccount::factory()->count(3)->create(['workspace_id' => $workspace->id]);

    User::factory()->count(2)->create(['account_id' => $this->account->id]);
    Invite::factory()->count(2)->create([
        'account_id' => $this->account->id,
        'invited_by' => $this->owner->id,
    ]);

    $usage = $this->account->usage();

    expect($usage)->toBe([
        'workspaceCount' => 2,
        'socialAccountCount' => 3,
        'memberCount' => 3,
        'pendingInviteCount' => 2,
        'creditsUsed' => 0,
    ]);
});

test('featureLimits returns plan-resolved limits', function () {
    $plan = Plan::where('slug', 'plus')->first();
    $this->account->update(['plan_id' => $plan->id]);

    $limits = $this->account->featureLimits();

    expect($limits)->toBe([
        'workspaceLimit' => $plan->workspace_limit,
        'socialAccountLimit' => $plan->social_account_limit,
        'memberLimit' => $plan->member_limit,
        'monthlyCreditsLimit' => $plan->monthly_credits_limit,
    ]);
});

test('pendingInviteCount excludes accepted invites', function () {
    Invite::factory()->create([
        'account_id' => $this->account->id,
        'invited_by' => $this->owner->id,
    ]);
    Invite::factory()->create([
        'account_id' => $this->account->id,
        'invited_by' => $this->owner->id,
        'accepted_at' => now(),
    ]);

    expect($this->account->usage()['pendingInviteCount'])->toBe(1);
});
