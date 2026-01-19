<?php

use App\Enums\UserWorkspace\Role;

test('role has correct labels', function () {
    expect(Role::Owner->label())->toBe('Owner');
    expect(Role::Admin->label())->toBe('Admin');
    expect(Role::Member->label())->toBe('Member');
});

test('owner can manage team', function () {
    expect(Role::Owner->canManageTeam())->toBeTrue();
});

test('admin can manage team', function () {
    expect(Role::Admin->canManageTeam())->toBeTrue();
});

test('member cannot manage team', function () {
    expect(Role::Member->canManageTeam())->toBeFalse();
});

test('owner can manage accounts', function () {
    expect(Role::Owner->canManageAccounts())->toBeTrue();
});

test('admin can manage accounts', function () {
    expect(Role::Admin->canManageAccounts())->toBeTrue();
});

test('member cannot manage accounts', function () {
    expect(Role::Member->canManageAccounts())->toBeFalse();
});

test('role has correct values', function () {
    expect(Role::Owner->value)->toBe('owner');
    expect(Role::Admin->value)->toBe('admin');
    expect(Role::Member->value)->toBe('member');
});
