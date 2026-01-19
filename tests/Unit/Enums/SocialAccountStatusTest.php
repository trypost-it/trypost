<?php

use App\Enums\SocialAccount\Status;

test('social account status has correct values', function () {
    expect(Status::Connected->value)->toBe('connected');
    expect(Status::Disconnected->value)->toBe('disconnected');
    expect(Status::TokenExpired->value)->toBe('token_expired');
});

test('social account status has labels', function () {
    expect(Status::Connected->label())->toBe('Connected');
    expect(Status::Disconnected->label())->toBe('Disconnected');
    expect(Status::TokenExpired->label())->toBe('Token Expired');
});

test('social account status has colors', function () {
    expect(Status::Connected->color())->toBe('green');
    expect(Status::Disconnected->color())->toBe('red');
    expect(Status::TokenExpired->color())->toBe('red');
});
