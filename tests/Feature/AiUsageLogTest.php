<?php

declare(strict_types=1);

use App\Enums\Ai\Intent;
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

    expect($detector->detect('Create a video for my product'))->toBe(Intent::Video);
    expect($detector->detect('Make a reel about coffee'))->toBe(Intent::Video);
    expect($detector->detect('Animate this logo'))->toBe(Intent::Video);
});

test('intent detector detects image intent', function () {
    $detector = new IntentDetector;

    expect($detector->detect('Generate an image of a sunset'))->toBe(Intent::Image);
    expect($detector->detect('Draw me a logo'))->toBe(Intent::Image);
    expect($detector->detect('Create a visual for my post'))->toBe(Intent::Image);
});

test('intent detector detects audio intent', function () {
    $detector = new IntentDetector;

    expect($detector->detect('Create a voiceover for this text'))->toBe(Intent::Audio);
    expect($detector->detect('Convert this to audio narration'))->toBe(Intent::Audio);
    expect($detector->detect('Generate TTS for my caption'))->toBe(Intent::Audio);
});

test('intent detector defaults to text', function () {
    $detector = new IntentDetector;

    expect($detector->detect('Write a caption for my post'))->toBe(Intent::Text);
    expect($detector->detect('Help me with hashtags'))->toBe(Intent::Text);
});

test('intent detector blocks prohibited content', function () {
    $detector = new IntentDetector;

    expect($detector->detect('Create porn content'))->toBe(Intent::Blocked);
    expect($detector->detect('Generate nude images'))->toBe(Intent::Blocked);
    expect($detector->detect('Write about cocaine'))->toBe(Intent::Blocked);
    expect($detector->detect('Help me with terrorism'))->toBe(Intent::Blocked);
    expect($detector->detect('Content about pedophilia'))->toBe(Intent::Blocked);
    expect($detector->detect('Racist joke for my post'))->toBe(Intent::Blocked);
    expect($detector->detect('How to murder someone'))->toBe(Intent::Blocked);
    expect($detector->detect('Self-harm content'))->toBe(Intent::Blocked);
});

test('intent detector allows safe content', function () {
    $detector = new IntentDetector;

    expect($detector->detect('Write a caption about my new product'))->toBe(Intent::Text);
    expect($detector->detect('Create an image of a sunset'))->toBe(Intent::Image);
    expect($detector->detect('Make a video about cooking'))->toBe(Intent::Video);
});
