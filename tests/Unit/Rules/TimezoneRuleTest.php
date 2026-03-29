<?php

use App\Rules\Timezone;
use Illuminate\Support\Facades\Validator;

test('accepts valid timezone', function () {
    $validator = Validator::make(['tz' => 'America/Sao_Paulo'], ['tz' => new Timezone]);

    expect($validator->passes())->toBeTrue();
});

test('accepts UTC', function () {
    $validator = Validator::make(['tz' => 'UTC'], ['tz' => new Timezone]);

    expect($validator->passes())->toBeTrue();
});

test('accepts deprecated timezone Asia/Calcutta', function () {
    $validator = Validator::make(['tz' => 'Asia/Calcutta'], ['tz' => new Timezone]);

    expect($validator->passes())->toBeTrue();
});

test('accepts deprecated timezone US/Eastern', function () {
    $validator = Validator::make(['tz' => 'US/Eastern'], ['tz' => new Timezone]);

    expect($validator->passes())->toBeTrue();
});

test('rejects invalid timezone', function () {
    $validator = Validator::make(['tz' => 'Invalid/Timezone'], ['tz' => new Timezone]);

    expect($validator->passes())->toBeFalse();
});

test('rejects random string', function () {
    $validator = Validator::make(['tz' => 'blabla'], ['tz' => new Timezone]);

    expect($validator->passes())->toBeFalse();
});

test('accepts Europe/London', function () {
    $validator = Validator::make(['tz' => 'Europe/London'], ['tz' => new Timezone]);

    expect($validator->passes())->toBeTrue();
});
