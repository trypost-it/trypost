<?php

declare(strict_types=1);

use App\Broadcasting\UserAiGenerationChannel;
use App\Models\User;

test('user can join their own generation channel', function () {
    $user = User::factory()->create();
    $channel = new UserAiGenerationChannel;

    expect($channel->join($user, $user->id, 'some-uuid'))->toBeTrue();
});

test('user cannot join another users generation channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $channel = new UserAiGenerationChannel;

    expect($channel->join($user, $other->id, 'some-uuid'))->toBeFalse();
});
