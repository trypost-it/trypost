<?php

declare(strict_types=1);

use App\Mcp\Servers\TryPostServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/trypost', TryPostServer::class)
    ->middleware('mcp.auth');
