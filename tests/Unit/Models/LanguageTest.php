<?php

use App\Models\Language;
use App\Models\User;

test('language has users relationship', function () {
    $language = Language::factory()->create();
    $user = User::factory()->create(['language_id' => $language->id]);

    expect($language->users)->toHaveCount(1);
    expect($language->users->first()->id)->toBe($user->id);
});

test('language has fillable attributes', function () {
    $language = Language::factory()->create([
        'name' => 'Portuguese',
        'code' => 'pt-BR',
    ]);

    expect($language->name)->toBe('Portuguese');
    expect($language->code)->toBe('pt-BR');
});
