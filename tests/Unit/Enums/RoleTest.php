<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;

test('role has correct labels', function () {
    expect(Role::Member->label())->toBe('Member');
    expect(Role::Viewer->label())->toBe('Viewer');
});

test('role has correct values', function () {
    expect(Role::Member->value)->toBe('member');
    expect(Role::Viewer->value)->toBe('viewer');
});
