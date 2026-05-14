<?php

declare(strict_types=1);

use App\Enums\Plan\Slug;

test('free slug exists and has label', function () {
    expect(Slug::Free->value)->toBe('free');
    expect(Slug::Free->label())->toBe('Free');
});
