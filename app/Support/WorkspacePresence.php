<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Tracks which workspace members are actively using the app in real time.
 *
 * Frontend pings `markOnline()` every ~30s while any authenticated workspace
 * page is mounted; the entry lives in the application's default cache store
 * for 60 seconds. Presence is consulted by NotifyMentions to skip the email
 * channel for users that are already online in the workspace (the in-app
 * bell already covers them).
 */
final class WorkspacePresence
{
    private const TTL_SECONDS = 60;

    public static function markOnline(string $workspaceId, string $userId): void
    {
        Cache::put(self::key($workspaceId, $userId), true, self::TTL_SECONDS);
    }

    public static function isOnline(string $workspaceId, string $userId): bool
    {
        return (bool) Cache::get(self::key($workspaceId, $userId), false);
    }

    private static function key(string $workspaceId, string $userId): string
    {
        return "presence:workspace:{$workspaceId}:user:{$userId}";
    }
}
