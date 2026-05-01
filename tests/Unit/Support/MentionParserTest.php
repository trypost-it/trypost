<?php

declare(strict_types=1);

use App\Support\MentionParser;

test('extracts a single uuid mention', function () {
    $body = 'Hey @[019dabc1-2345-6789-abcd-ef0123456789] take a look';

    expect(MentionParser::extractUserIds($body))
        ->toEqual(['019dabc1-2345-6789-abcd-ef0123456789']);
});

test('extracts multiple distinct mentions in order of first appearance', function () {
    $body = '@[aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa] and @[bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb]';

    expect(MentionParser::extractUserIds($body))
        ->toEqual(['aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb']);
});

test('dedupes repeated mentions of the same uuid', function () {
    $id = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
    $body = "@[$id] @[$id] middle @[$id]";

    expect(MentionParser::extractUserIds($body))->toEqual([$id]);
});

test('returns empty array for body with no markers', function () {
    expect(MentionParser::extractUserIds('No mentions in this text @notuuid'))->toEqual([]);
});

test('ignores malformed uuids', function () {
    $body = '@[not-a-uuid] @[019d] @[XX] real -> @[12345678-1234-1234-1234-123456789abc]';

    expect(MentionParser::extractUserIds($body))
        ->toEqual(['12345678-1234-1234-1234-123456789abc']);
});

test('matches uuids regardless of hex case', function () {
    $body = '@[ABCDEFAB-cdef-CDEF-1234-1234567890ab]';

    expect(MentionParser::extractUserIds($body))
        ->toEqual(['ABCDEFAB-cdef-CDEF-1234-1234567890ab']);
});

test('returns empty for empty body', function () {
    expect(MentionParser::extractUserIds(''))->toEqual([]);
});
