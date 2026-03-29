<?php

declare(strict_types=1);

use App\Mcp\Servers\TryPostServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::group(
    [
        'domain' => 'mcp.'.parse_url(config('app.url'), PHP_URL_HOST),
    ],
    function () {
        Mcp::web('/trypost', TryPostServer::class)
            ->middleware('mcp.auth');
    }
);
