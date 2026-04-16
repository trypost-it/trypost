<?php

declare(strict_types=1);

use App\Enums\User\Setup;

test('user setup has correct values', function () {
    expect(Setup::Registering->value)->toBe('registering');
    expect(Setup::Role->value)->toBe('role');
    expect(Setup::Brand->value)->toBe('brand');
    expect(Setup::Connections->value)->toBe('connections');
    expect(Setup::Subscription->value)->toBe('subscription');
    expect(Setup::Completed->value)->toBe('completed');
});

test('user setup has labels', function () {
    expect(Setup::Registering->label())->toBe('Registering');
    expect(Setup::Role->label())->toBe('Select Role');
    expect(Setup::Brand->label())->toBe('Configure Brand');
    expect(Setup::Connections->label())->toBe('Connect Accounts');
    expect(Setup::Subscription->label())->toBe('Start Subscription');
    expect(Setup::Completed->label())->toBe('Completed');
});

test('user setup has step numbers', function () {
    expect(Setup::Registering->stepNumber())->toBe(0);
    expect(Setup::Role->stepNumber())->toBe(1);
    expect(Setup::Brand->stepNumber())->toBe(2);
    expect(Setup::Connections->stepNumber())->toBe(3);
    expect(Setup::Subscription->stepNumber())->toBe(4);
    expect(Setup::Completed->stepNumber())->toBe(5);
});
