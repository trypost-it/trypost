<?php

use App\Events\SubscriptionCreated;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;

test('event broadcasts on correct channel', function () {
    $user = User::factory()->create();
    $event = new SubscriptionCreated($user);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-users.'.$user->id);
});

test('event broadcasts with correct data', function () {
    $user = User::factory()->create();
    $event = new SubscriptionCreated($user);
    $data = $event->broadcastWith();

    expect($data)->toHaveKey('status');
    expect($data)->toHaveKey('message');
    expect($data['status'])->toBe('success');
    expect($data['message'])->toBe('Subscription created successfully');
});
