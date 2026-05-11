<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Rules\ContentFitsPlatformLimits;

function runFitsRule(string $content, array $platforms): array
{
    $errors = [];
    $rule = new ContentFitsPlatformLimits(collect($platforms));

    $rule->validate('content', $content, function (string $message) use (&$errors): void {
        $errors[] = $message;
    });

    return $errors;
}

test('passes when content fits every platform cap', function () {
    $errors = runFitsRule(str_repeat('a', 280), [Platform::X, Platform::Threads, Platform::Facebook]);

    expect($errors)->toBe([]);
});

test('fails with the platform label, limit and overage when content exceeds a single platform', function () {
    $errors = runFitsRule(str_repeat('a', 537), [Platform::Threads]);

    expect($errors)->toHaveCount(1);
    expect($errors[0])
        ->toContain('Threads')
        ->toContain('500')
        ->toContain('37');
});

test('emits one error per overflowing platform in a multi-platform set', function () {
    // 320 chars: fine for Threads (500), over for X (280) and Bluesky (300).
    $errors = runFitsRule(str_repeat('a', 320), [Platform::X, Platform::Bluesky, Platform::Threads]);

    expect($errors)->toHaveCount(2);
    expect($errors[0])->toContain('X');
    expect($errors[1])->toContain('Bluesky');
});

test('deduplicates errors when the same platform appears twice in the collection', function () {
    // Two Threads accounts selected, content 600 chars — should still produce ONE error.
    $errors = runFitsRule(str_repeat('a', 600), [Platform::Threads, Platform::Threads]);

    expect($errors)->toHaveCount(1);
});

test('passes for an empty platforms collection', function () {
    $errors = runFitsRule(str_repeat('a', 10_000), []);

    expect($errors)->toBe([]);
});

test('treats null content as an empty string and passes', function () {
    $errors = runFitsRule('', [Platform::Threads, Platform::X]);

    expect($errors)->toBe([]);
});
