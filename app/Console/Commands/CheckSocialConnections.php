<?php

namespace App\Console\Commands;

use App\Enums\SocialAccount\Status;
use App\Jobs\VerifyWorkspaceConnections;
use App\Models\Workspace;
use Illuminate\Console\Command;

class CheckSocialConnections extends Command
{
    protected $signature = 'social:check-connections';

    protected $description = 'Verify that all connected social accounts have valid tokens';

    public function handle(): void
    {
        Workspace::query()
            ->whereHas('socialAccounts', function ($query) {
                $query->where('status', Status::Connected);
            })
            ->with('owner')
            ->chunk(100, function ($workspaces) {
                foreach ($workspaces as $workspace) {
                    VerifyWorkspaceConnections::dispatch($workspace);
                }
            });
    }
}
