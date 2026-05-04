<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Concerns;

use Illuminate\Http\Request;

trait PreservesUtmParameters
{
    private const array UTM_KEYS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

    /**
     * @return array<string, string>
     */
    private function extractUtmParameters(Request $request): array
    {
        return array_filter(
            array_map(
                fn (string $value) => mb_substr($value, 0, 255),
                array_filter($request->only(self::UTM_KEYS), 'is_string'),
            ),
        );
    }

    private function storeUtmParameters(Request $request): void
    {
        $utms = $this->extractUtmParameters($request);

        if ($utms !== []) {
            $request->session()->put('utm_parameters', $utms);
        }
    }

    /**
     * @return array<string, string>
     */
    private function retrieveUtmParameters(): array
    {
        return session()->pull('utm_parameters', []);
    }
}
