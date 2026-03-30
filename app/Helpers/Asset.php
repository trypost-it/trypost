<?php

declare(strict_types=1);

if (! function_exists('email_asset')) {
    /**
     * Generate an absolute URL for email assets.
     */
    function email_asset(string $path): string
    {
        return rtrim(config('app.email_asset_url'), '/').'/'.ltrim($path, '/');
    }
}
