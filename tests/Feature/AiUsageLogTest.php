<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\AiUsageLog;
use App\Models\Workspace;
use App\Services\Ai\IntentDetector;

test('monthly count returns correct count for account and type', function () {
    $account = Account::factory()->create();
    $workspace = Workspace::factory()->create(['account_id' => $account->id]);

    AiUsageLog::factory()->image()->count(3)->create([
        'account_id' => $account->id,
        'workspace_id' => $workspace->id,
    ]);

    AiUsageLog::factory()->video()->count(2)->create([
        'account_id' => $account->id,
        'workspace_id' => $workspace->id,
    ]);

    expect(AiUsageLog::monthlyCount($account->id, 'image'))->toBe(3);
    expect(AiUsageLog::monthlyCount($account->id, 'video'))->toBe(2);
    expect(AiUsageLog::monthlyCount($account->id, 'audio'))->toBe(0);
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

    expect(AiUsageLog::monthlyCount($account->id, 'image'))->toBe(1);
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

    expect(AiUsageLog::monthlyCount($account->id, 'image'))->toBe(1);
});

test('intent detector detects video intent', function () {
    $detector = new IntentDetector;

    expect($detector->detect('Create a video for my product'))->toBe('video');
    expect($detector->detect('Make a reel about coffee'))->toBe('video');
    expect($detector->detect('Animate this logo'))->toBe('video');
});

test('intent detector detects image intent', function () {
    $detector = new IntentDetector;

    expect($detector->detect('Generate an image of a sunset'))->toBe('image');
    expect($detector->detect('Draw me a logo'))->toBe('image');
    expect($detector->detect('Create a visual for my post'))->toBe('image');
});

test('intent detector detects audio intent', function () {
    $detector = new IntentDetector;

    expect($detector->detect('Create a voiceover for this text'))->toBe('audio');
    expect($detector->detect('Convert this to audio narration'))->toBe('audio');
    expect($detector->detect('Generate TTS for my caption'))->toBe('audio');
});

test('intent detector defaults to text', function () {
    $detector = new IntentDetector;

    expect($detector->detect('Write a caption for my post'))->toBe('text');
    expect($detector->detect('Help me with hashtags'))->toBe('text');
});
