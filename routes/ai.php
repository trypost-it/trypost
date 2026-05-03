<?php

declare(strict_types=1);

use App\Mcp\Servers\TryPostServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp/trypost', TryPostServer::class)
    ->middleware(['auth:api', 'workspace.token']);
