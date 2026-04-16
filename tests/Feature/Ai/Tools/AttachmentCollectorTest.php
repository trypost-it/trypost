<?php

declare(strict_types=1);

use App\Ai\Tools\AttachmentCollector;

test('collector starts empty', function () {
    $collector = new AttachmentCollector;

    expect($collector->all())->toBeEmpty();
});

test('collector records attachments in push order', function () {
    $collector = new AttachmentCollector;

    $collector->push(['id' => 'a', 'type' => 'image']);
    $collector->push(['id' => 'b', 'type' => 'video']);

    expect($collector->all())->toHaveCount(2);
    expect($collector->all()[0]['id'])->toBe('a');
    expect($collector->all()[1]['id'])->toBe('b');
});

test('collector clear removes all attachments', function () {
    $collector = new AttachmentCollector;

    $collector->push(['id' => 'a', 'type' => 'image']);
    $collector->clear();

    expect($collector->all())->toBeEmpty();
});

test('collector resolves as a scoped singleton within the container', function () {
    $a = app(AttachmentCollector::class);
    $b = app(AttachmentCollector::class);

    $a->push(['id' => 'same', 'type' => 'image']);

    expect($b->all())->toHaveCount(1);
    expect($b->all()[0]['id'])->toBe('same');
});
