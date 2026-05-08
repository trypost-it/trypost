<?php

declare(strict_types=1);

namespace App\Services\Brand;

/**
 * Extracts the most representative non-neutral colour from a CSS blob by
 * counting occurrences and clustering perceptually similar shades together
 * in CIE LAB space. Solves the Tailwind/utility-CSS case where the brand
 * colour appears hundreds of times as `bg-blue-700`/`border-blue-700`/etc
 * but no semantic --primary variable is exposed.
 *
 * Pipeline:
 *   1. Regex-extract every #hex/rgb()/rgba()/hsl()/hsla() value from the CSS
 *   2. Normalise each to lowercase #rrggbb
 *   3. Convert to LAB and cluster colours within `delta E (CIE76) < 12`
 *   4. Drop clusters whose centre is neutral (low channel spread)
 *   5. Return the centre of the largest remaining cluster
 *
 * CIE76 (Euclidean ΔE in LAB) is intentional — DE2000 is the print-grade
 * gold standard but adds complexity for marginal accuracy at the "are
 * these the same brand colour?" task we're solving here.
 */
final class CssColorFrequencyExtractor
{
    private const int CLUSTER_THRESHOLD = 12;

    private const int NEUTRAL_CHANNEL_DELTA = 18;

    public function extract(string $css): ?string
    {
        $occurrences = $this->countOccurrences($css);
        if ($occurrences === []) {
            return null;
        }

        $clusters = $this->clusterPerceptually($occurrences);

        // Drop clusters whose representative colour is neutral.
        $clusters = array_values(array_filter(
            $clusters,
            fn (array $cluster): bool => ! $this->isNeutral($cluster['hex']),
        ));

        if ($clusters === []) {
            return null;
        }

        usort($clusters, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $clusters[0]['hex'];
    }

    /**
     * @return array<string, int> Hex colour => occurrence count.
     */
    private function countOccurrences(string $css): array
    {
        $counts = [];

        $patterns = [
            '/#([0-9a-fA-F]{8})\b/' => fn (array $m): ?string => $this->normaliseHex(substr($m[1], 0, 6)),
            '/#([0-9a-fA-F]{6})\b/' => fn (array $m): ?string => $this->normaliseHex($m[1]),
            '/#([0-9a-fA-F]{3})\b/' => function (array $m): ?string {
                $h = $m[1];

                return $this->normaliseHex($h[0].$h[0].$h[1].$h[1].$h[2].$h[2]);
            },
            '/rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})/i' => fn (array $m): ?string => $this->rgbToHex((int) $m[1], (int) $m[2], (int) $m[3]),
            '/hsla?\(\s*(\d{1,3}(?:\.\d+)?)\s*,?\s*(\d{1,3}(?:\.\d+)?)%\s*,?\s*(\d{1,3}(?:\.\d+)?)%/i' => fn (array $m): ?string => $this->hslToHex((float) $m[1], (float) $m[2], (float) $m[3]),
        ];

        foreach ($patterns as $pattern => $convert) {
            if (preg_match_all($pattern, $css, $matches, PREG_SET_ORDER) === false) {
                continue;
            }
            foreach ($matches as $match) {
                $hex = $convert($match);
                if ($hex !== null) {
                    $counts[$hex] = ($counts[$hex] ?? 0) + 1;
                }
            }
        }

        return $counts;
    }

    /**
     * Group colours by perceptual similarity (CIE76 ΔE) and sum their counts.
     *
     * @param  array<string, int>  $occurrences
     * @return list<array{hex: string, count: int}>
     */
    private function clusterPerceptually(array $occurrences): array
    {
        // Sort entries so the most frequent colour anchors each cluster.
        arsort($occurrences);

        $clusters = [];

        foreach ($occurrences as $hex => $count) {
            $lab = $this->hexToLab($hex);
            $merged = false;

            foreach ($clusters as &$cluster) {
                if ($this->deltaE76($lab, $cluster['lab']) < self::CLUSTER_THRESHOLD) {
                    $cluster['count'] += $count;
                    $merged = true;
                    break;
                }
            }
            unset($cluster);

            if (! $merged) {
                $clusters[] = ['hex' => $hex, 'lab' => $lab, 'count' => $count];
            }
        }

        return array_map(
            fn (array $c): array => ['hex' => $c['hex'], 'count' => $c['count']],
            $clusters,
        );
    }

    private function isNeutral(string $hex): bool
    {
        $hex = ltrim($hex, '#');
        $r = (int) hexdec(substr($hex, 0, 2));
        $g = (int) hexdec(substr($hex, 2, 2));
        $b = (int) hexdec(substr($hex, 4, 2));

        $maxDelta = max(abs($r - $g), abs($g - $b), abs($r - $b));

        return $maxDelta < self::NEUTRAL_CHANNEL_DELTA;
    }

    private function normaliseHex(string $hex6): ?string
    {
        if (! ctype_xdigit($hex6) || strlen($hex6) !== 6) {
            return null;
        }

        return '#'.strtolower($hex6);
    }

    private function rgbToHex(int $r, int $g, int $b): ?string
    {
        if ($r > 255 || $g > 255 || $b > 255) {
            return null;
        }

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * @return string|null Hex colour.
     */
    private function hslToHex(float $h, float $s, float $l): ?string
    {
        if ($h > 360 || $s > 100 || $l > 100) {
            return null;
        }

        $h /= 360;
        $s /= 100;
        $l /= 100;

        if ($s === 0.0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = $this->hueToRgb($p, $q, $h + 1 / 3);
            $g = $this->hueToRgb($p, $q, $h);
            $b = $this->hueToRgb($p, $q, $h - 1 / 3);
        }

        return $this->rgbToHex(
            (int) round($r * 255),
            (int) round($g * 255),
            (int) round($b * 255),
        );
    }

    private function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $p;
    }

    /**
     * Convert hex to CIE LAB. Goes via sRGB → XYZ (D65) → LAB.
     *
     * @return array{0: float, 1: float, 2: float}
     */
    private function hexToLab(string $hex): array
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        // sRGB → linear RGB
        $r = $r > 0.04045 ? (($r + 0.055) / 1.055) ** 2.4 : $r / 12.92;
        $g = $g > 0.04045 ? (($g + 0.055) / 1.055) ** 2.4 : $g / 12.92;
        $b = $b > 0.04045 ? (($b + 0.055) / 1.055) ** 2.4 : $b / 12.92;

        // Linear RGB → XYZ (D65)
        $x = ($r * 0.4124564 + $g * 0.3575761 + $b * 0.1804375) / 0.95047;
        $y = ($r * 0.2126729 + $g * 0.7151522 + $b * 0.0721750) / 1.00000;
        $z = ($r * 0.0193339 + $g * 0.1191920 + $b * 0.9503041) / 1.08883;

        $f = fn (float $t): float => $t > 0.008856 ? $t ** (1 / 3) : (7.787 * $t) + 16 / 116;
        $fx = $f($x);
        $fy = $f($y);
        $fz = $f($z);

        return [
            116 * $fy - 16,
            500 * ($fx - $fy),
            200 * ($fy - $fz),
        ];
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $lab1
     * @param  array{0: float, 1: float, 2: float}  $lab2
     */
    private function deltaE76(array $lab1, array $lab2): float
    {
        return sqrt(
            ($lab1[0] - $lab2[0]) ** 2 +
            ($lab1[1] - $lab2[1]) ** 2 +
            ($lab1[2] - $lab2[2]) ** 2,
        );
    }
}
