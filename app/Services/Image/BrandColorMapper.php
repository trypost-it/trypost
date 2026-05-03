<?php

declare(strict_types=1);

namespace App\Services\Image;

use App\Models\Workspace;

class BrandColorMapper
{
    /**
     * Convert a workspace's brand color (hex) to an Unsplash color bucket.
     * Falls back to background_color, then null. Null = no color filter.
     */
    public function fromWorkspace(Workspace $workspace): ?string
    {
        $hex = $workspace->brand_color ?: $workspace->background_color;

        return $hex ? $this->fromHex($hex) : null;
    }

    public function fromHex(string $hex): ?string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return null;
        }

        [$r, $g, $b] = [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];

        [$h, $s, $l] = $this->rgbToHsl($r, $g, $b);

        // Grayscale handling
        if ($s < 0.10) {
            if ($l < 0.30) {
                return 'black';
            }
            if ($l > 0.70) {
                return 'white';
            }

            return 'black_and_white';
        }

        $hueDeg = $h * 360;

        return match (true) {
            $hueDeg >= 345 || $hueDeg < 15 => 'red',
            $hueDeg < 45 => 'orange',
            $hueDeg < 65 => 'yellow',
            $hueDeg < 150 => 'green',
            $hueDeg < 200 => 'teal',
            $hueDeg < 260 => 'blue',
            $hueDeg < 300 => 'purple',
            $hueDeg < 345 => 'magenta',
            default => null,
        };
    }

    /** @return array{0: float, 1: float, 2: float} */
    private function rgbToHsl(int $r, int $g, int $b): array
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0, 0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $r => ($g - $b) / $d + ($g < $b ? 6 : 0),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };
        $h /= 6;

        return [$h, $s, $l];
    }
}
