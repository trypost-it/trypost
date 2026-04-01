<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Services\Social\ContentSanitizer;

test('it strips html tags for plain text platforms', function () {
    $sanitizer = new ContentSanitizer;
    $result = $sanitizer->sanitize('<p>Hello <strong>world</strong></p>', Platform::Instagram);
    expect($result)->toBe('Hello world');
});

test('it converts bold to unicode for linkedin', function () {
    $sanitizer = new ContentSanitizer;
    $result = $sanitizer->sanitize('<p>Hello <strong>world</strong></p>', Platform::LinkedIn);
    expect($result)->toContain('𝘄𝗼𝗿𝗹𝗱');
});

test('it converts p tags to newlines', function () {
    $sanitizer = new ContentSanitizer;
    $result = $sanitizer->sanitize('<p>First paragraph</p><p>Second paragraph</p>', Platform::X);
    expect($result)->toBe("First paragraph\nSecond paragraph");
});

test('it converts br to newlines', function () {
    $sanitizer = new ContentSanitizer;
    $result = $sanitizer->sanitize('Line one<br>Line two', Platform::Facebook);
    expect($result)->toBe("Line one\nLine two");
});

test('it decodes html entities', function () {
    $sanitizer = new ContentSanitizer;
    $result = $sanitizer->sanitize('Tom &amp; Jerry &lt;3', Platform::X);
    expect($result)->toBe('Tom & Jerry <3');
});

test('it returns plain text unchanged', function () {
    $sanitizer = new ContentSanitizer;
    $result = $sanitizer->sanitize('Just plain text', Platform::Instagram);
    expect($result)->toBe('Just plain text');
});

test('it handles null-safe empty content', function () {
    // The publisher handles null check, sanitizer should handle empty string
    $sanitizer = new ContentSanitizer;
    $result = $sanitizer->sanitize('', Platform::X);
    expect($result)->toBe('');
});

test('it converts list items to dashes', function () {
    $sanitizer = new ContentSanitizer;
    $result = $sanitizer->sanitize('<ul><li>Item one</li><li>Item two</li></ul>', Platform::Facebook);
    expect($result)->toContain('- Item one');
    expect($result)->toContain('- Item two');
});

test('it preserves safe html for mastodon', function () {
    $sanitizer = new ContentSanitizer;
    $result = $sanitizer->sanitize('<p>Hello <strong>world</strong> and <em>italic</em></p>', Platform::Mastodon);
    expect($result)->toContain('<strong>world</strong>');
    expect($result)->toContain('<em>italic</em>');
    expect($result)->toContain('<p>');
});

test('it strips unsafe html for mastodon', function () {
    $sanitizer = new ContentSanitizer;
    $result = $sanitizer->sanitize('<p>Hello</p><script>alert("xss")</script><div>block</div>', Platform::Mastodon);
    expect($result)->toContain('<p>Hello</p>');
    expect($result)->not->toContain('<script>');
    expect($result)->not->toContain('<div>');
});

test('it preserves links for mastodon', function () {
    $sanitizer = new ContentSanitizer;
    $result = $sanitizer->sanitize('<p>Check <a href="https://example.com">this</a></p>', Platform::Mastodon);
    expect($result)->toContain('<a href="https://example.com">this</a>');
});
