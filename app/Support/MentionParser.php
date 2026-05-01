<?php

declare(strict_types=1);

namespace App\Support;

final class MentionParser
{
    /**
     * Marker syntax for mentions inside a comment body.
     *
     *     "Hey @[019dabc...] could you review?"
     *
     * The frontend stores the user_id wrapped in `@[ ]` so that downstream
     * rendering and notification dispatch never depend on a free-form display
     * name (which can change or be ambiguous within a workspace).
     */
    private const PATTERN = '/@\[([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})\]/';

    /**
     * Extract the unique user ids referenced in the given comment body.
     *
     * @return array<int, string>
     */
    public static function extractUserIds(string $body): array
    {
        if (! preg_match_all(self::PATTERN, $body, $matches)) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }
}
