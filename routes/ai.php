<?php

declare(strict_types=1);

use App\Mcp\Servers\postproServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp/postpro', postproServer::class)
    ->middleware(['auth:api', 'workspace.token']);

