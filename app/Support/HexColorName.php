<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Maps a hex colour to a human-readable approximate name. Image generation
 * models follow colour names ("warm orange", "deep teal") far more reliably
 * than raw hex codes, so we translate the brand colour into the closest
 * named bucket before injecting it into the prompt.
 *
 * Returns null when the hex is malformed.
 */
class HexColorName
{
    public static function approximate(string $hex): ?string
    {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) === 8) {
            $hex = substr($hex, 0, 6);
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return null;
        }

        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        [$h, $s, $l] = self::rgbToHsl($r, $g, $b);

        // Low-saturation neutrals.
        if ($s < 0.10) {
            return match (true) {
                $l < 0.10 => 'near-black',
                $l < 0.30 => 'dark gray',
                $l < 0.70 => 'medium gray',
                $l < 0.90 => 'light gray',
                default => 'off-white',
            };
        }

        $hue = $h * 360;

        $base = match (true) {
            $hue < 10 || $hue >= 345 => 'red',
            $hue < 25 => 'red-orange',
            $hue < 45 => 'warm orange',
            $hue < 65 => 'golden yellow',
            $hue < 90 => 'yellow-green',
            $hue < 150 => 'green',
            $hue < 180 => 'teal',
            $hue < 210 => 'cyan',
            $hue < 240 => 'blue',
            $hue < 270 => 'indigo',
            $hue < 300 => 'purple',
            $hue < 345 => 'magenta',
            default => 'red',
        };

        $modifier = match (true) {
            $l < 0.25 => 'deep ',
            $l > 0.75 => 'light ',
            default => '',
        };

        return $modifier.$base;
    }

    /**
     * @return array{0: float, 1: float, 2: float} HSL components on a 0..1 scale.
     */
    private static function rgbToHsl(float $r, float $g, float $b): array
    {
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match (true) {
            $max === $r => ($g - $b) / $d + ($g < $b ? 6 : 0),
            $max === $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };
        $h /= 6;

        return [$h, $s, $l];
    }
}
