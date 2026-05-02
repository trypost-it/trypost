<?php

declare(strict_types=1);

namespace App\Services\Brand;

use Illuminate\Support\Facades\Log;
use League\ColorExtractor\Color;
use League\ColorExtractor\ColorExtractor;
use League\ColorExtractor\Palette;
use Throwable;

/**
 * Pulls the dominant non-neutral color out of a downloaded logo image. This is
 * the same technique used by Brandfetch and the `color-thief` JavaScript
 * library: quantize pixels into buckets, sort by frequency, then drop the
 * grays/blacks/whites that show up in nearly every logo.
 */
final class LogoColorExtractor
{
    private const int TOP_COLORS = 10;

    public function __construct(private readonly SafeHttpFetcher $fetcher) {}

    public function extractFromUrl(?string $logoUrl): ?string
    {
        if ($logoUrl === null || $logoUrl === '') {
            return null;
        }

        $response = $this->fetcher->tryGet($logoUrl);
        if ($response === null || ! $response->successful()) {
            return null;
        }

        $body = $response->body();
        if ($body === '') {
            return null;
        }

        // league/color-extractor relies on GD, which doesn't speak SVG/ICO.
        $contentType = strtolower((string) $response->header('Content-Type'));
        if (str_contains($contentType, 'svg') || str_contains($contentType, 'icon')) {
            return null;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'logo_color_');
        file_put_contents($tempFile, $body);

        try {
            $palette = Palette::fromFilename($tempFile);
            $extractor = new ColorExtractor($palette);
            $colors = $extractor->extract(self::TOP_COLORS);

            foreach ($colors as $colorInt) {
                $hex = strtolower(Color::fromIntToHex($colorInt));
                if (! $this->isNeutral($hex)) {
                    return $hex;
                }
            }

            return null;
        } catch (Throwable $e) {
            Log::warning('LogoColorExtractor failed', [
                'url' => $logoUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Reject true black/white and any color whose channels are within 18 points
     * of each other (i.e. essentially gray). This filters out the dominant
     * background a logo typically sits on.
     */
    private function isNeutral(string $hex): bool
    {
        if (strlen($hex) !== 7 || $hex[0] !== '#') {
            return true;
        }

        $r = (int) hexdec(substr($hex, 1, 2));
        $g = (int) hexdec(substr($hex, 3, 2));
        $b = (int) hexdec(substr($hex, 5, 2));

        $maxDelta = max(abs($r - $g), abs($g - $b), abs($r - $b));

        return $maxDelta < 18;
    }
}
