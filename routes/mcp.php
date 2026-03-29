<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group(
    [
        'domain' => 'mcp.'.parse_url(config('app.url'), PHP_URL_HOST),
    ],
    function () {
        //
    }
);
