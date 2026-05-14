<?php

declare(strict_types=1);

namespace App\Actions\Workspace;

use App\Models\Workspace;

class WorkspaceDeletionImpact
{
    /**
     * @return array{posts: int, social_accounts: int, labels: int, signatures: int, members: int}
     */
    public static function execute(Workspace $workspace): array
    {
        return [
            'posts' => (int) $workspace->posts()->count(),
            'social_accounts' => (int) $workspace->socialAccounts()->count(),
            'labels' => (int) $workspace->labels()->count(),
            'signatures' => (int) $workspace->signatures()->count(),
            'members' => (int) $workspace->members()->count(),
        ];
    }
}
