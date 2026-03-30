<?php

declare(strict_types=1);

namespace App\Http\Middleware\App;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $available = config('languages.available');
        $locale = $request->cookie('locale');
        $isValid = $locale && array_key_exists($locale, $available);

        App::setLocale($isValid ? $locale : config('languages.default'));

        $response = $next($request);

        if (! $isValid) {
            $response->withCookie(
                cookie()->forever('locale', config('languages.default'), '/', config('session.domain')),
            );
        }

        return $response;
    }
}
