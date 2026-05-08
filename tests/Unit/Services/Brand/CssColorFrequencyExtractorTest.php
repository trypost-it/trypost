<?php

declare(strict_types=1);

use App\Services\Brand\CssColorFrequencyExtractor;

beforeEach(function () {
    $this->extractor = new CssColorFrequencyExtractor;
});

test('returns null when CSS has no colour values', function () {
    expect($this->extractor->extract('body { padding: 20px; }'))->toBeNull();
});

test('extracts the most frequent hex colour', function () {
    $css = str_repeat('color: #1e40af; ', 50)
        .str_repeat('color: #dc2626; ', 5);

    expect($this->extractor->extract($css))->toBe('#1e40af');
});

test('clusters perceptually similar shades and sums their counts', function () {
    // 30× a brand blue PLUS 20× a slightly different shade of the same blue
    // should still resolve to a blue cluster, not lose to an unrelated colour
    // that happens to appear 40 times standalone.
    $css = str_repeat('color: #1e40af; ', 30)
        .str_repeat('color: #1d3fae; ', 20)  // ΔE76 ~1 — clusters with above
        .str_repeat('color: #16a34a; ', 40);

    expect($this->extractor->extract($css))->toBe('#1e40af');
});

test('filters neutral greys/blacks/whites out of the result', function () {
    $css = str_repeat('color: #000000; ', 200)
        .str_repeat('color: #ffffff; ', 200)
        .str_repeat('color: #888888; ', 200)
        .str_repeat('color: #f47b20; ', 5);

    expect($this->extractor->extract($css))->toBe('#f47b20');
});

test('parses rgb() and rgba() values', function () {
    $css = str_repeat('color: rgb(30, 64, 175); ', 50)
        .str_repeat('color: rgba(220, 38, 38, 0.5); ', 5);

    expect($this->extractor->extract($css))->toBe('#1e40af');
});

test('parses hsl() values', function () {
    // hsl(220, 70%, 40%) ≈ #1f4ec7 (a clear blue)
    $css = str_repeat('color: hsl(220, 70%, 40%); ', 50)
        .str_repeat('color: hsl(0, 70%, 40%); ', 5);

    expect($this->extractor->extract($css))->toStartWith('#');
});

test('parses 3-char hex shorthand', function () {
    $css = str_repeat('color: #f80; ', 50);

    expect($this->extractor->extract($css))->toBe('#ff8800');
});

test('strips alpha from 8-char hex', function () {
    $css = str_repeat('color: #f47b20ff; ', 50);

    expect($this->extractor->extract($css))->toBe('#f47b20');
});

test('returns null when only neutrals are present', function () {
    $css = str_repeat('color: #000000; ', 50)
        .str_repeat('color: #ffffff; ', 50)
        .str_repeat('color: #888888; ', 50);

    expect($this->extractor->extract($css))->toBeNull();
});
