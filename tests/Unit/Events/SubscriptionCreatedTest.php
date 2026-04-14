<?php

declare(strict_types=1);

use App\Events\SubscriptionCreated;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Broadcasting\PrivateChannel;

test('event broadcasts on correct channel', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $event = new SubscriptionCreated($workspace);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-users.'.$user->id);
});

test('event broadcasts with correct data', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $event = new SubscriptionCreated($workspace);
    $data = $event->broadcastWith();

    expect($data)->toHaveKey('status');
    expect($data)->toHaveKey('message');
    expect($data['status'])->toBe('success');
    expect($data['message'])->toBe('Subscription created successfully');
});
