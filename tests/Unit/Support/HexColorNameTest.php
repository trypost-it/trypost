<?php

declare(strict_types=1);

use App\Support\HexColorName;

test('returns null for malformed hex', function () {
    expect(HexColorName::approximate(''))->toBeNull();
    expect(HexColorName::approximate('#xyz'))->toBeNull();
    expect(HexColorName::approximate('#12345'))->toBeNull();
    expect(HexColorName::approximate('not-a-hex'))->toBeNull();
});

test('handles 3-char shorthand and alpha hex', function () {
    expect(HexColorName::approximate('#fff'))->toBe('off-white');
    expect(HexColorName::approximate('#000000FF'))->toBe('near-black');
});

test('classifies neutrals by lightness', function () {
    expect(HexColorName::approximate('#000000'))->toBe('near-black');
    expect(HexColorName::approximate('#333333'))->toBe('dark gray');
    expect(HexColorName::approximate('#888888'))->toBe('medium gray');
    expect(HexColorName::approximate('#cccccc'))->toBe('light gray');
    expect(HexColorName::approximate('#ffffff'))->toBe('off-white');
});

test('maps warm hues to expected names', function () {
    expect(HexColorName::approximate('#ff0000'))->toBe('red');
    expect(HexColorName::approximate('#f47b20'))->toBe('warm orange');
    expect(HexColorName::approximate('#ffd100'))->toBe('golden yellow');
});

test('maps cool hues to expected names', function () {
    expect(HexColorName::approximate('#00ff00'))->toBe('green');
    expect(HexColorName::approximate('#00bcd4'))->toBe('cyan');
    expect(HexColorName::approximate('#0066ff'))->toBe('blue');
    expect(HexColorName::approximate('#7a3cff'))->toBe('indigo');
});

test('applies deep modifier for dark variants', function () {
    expect(HexColorName::approximate('#330000'))->toBe('deep red');
    expect(HexColorName::approximate('#001f3f'))->toBe('deep blue');
});

test('applies light modifier for pale variants', function () {
    expect(HexColorName::approximate('#ffd99e'))->toBe('light warm orange');
    expect(HexColorName::approximate('#ffd6c2'))->toBe('light red-orange');
});
