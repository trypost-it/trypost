<?php

declare(strict_types=1);

use App\Services\PostTemplate\Registry;

/**
 * Integrity gate for the file-based post-templates system. Replaces the DB-side
 * enforcement that schemas used to provide. Runs in CI; failures block merge.
 *
 * The five invariants:
 *   1) Every locale has the same count of templates per platform.
 *   2) Every locale has the same set of slugs per platform.
 *   3) Structural fields (category, image_count, slide count, slide image_keywords)
 *      match across locales for every slug.
 *   4) No duplicate slugs within a single (locale, platform) file.
 *   5) Every category referenced is in the known set.
 */
const KNOWN_CATEGORIES = [
    'product_launch',
    'promotion',
    'educational',
    'behind_the_scenes',
    'testimonial',
    'industry_tip',
    'event',
    'engagement',
];

function loadPlatformFile(string $locale, string $platform): array
{
    $path = base_path("templates/{$locale}/{$platform}.php");

    return is_file($path) ? require $path : [];
}

function discoverLocales(): array
{
    $dirs = glob(base_path('templates').'/*', GLOB_ONLYDIR) ?: [];

    return array_map(fn (string $d) => basename($d), $dirs);
}

function discoverPlatforms(): array
{
    $files = glob(base_path('templates/'.Registry::DEFAULT_LOCALE).'/*.php') ?: [];

    return array_map(fn (string $f) => basename($f, '.php'), $files);
}

it('has the same number of templates per platform across all locales', function () {
    foreach (discoverPlatforms() as $platform) {
        $counts = [];
        foreach (discoverLocales() as $locale) {
            $counts[$locale] = count(loadPlatformFile($locale, $platform));
        }

        expect(array_unique(array_values($counts)))->toHaveCount(
            1,
            "Platform {$platform} has divergent counts: ".json_encode($counts),
        );
    }
});

it('has the same set of slugs per platform across all locales', function () {
    foreach (discoverPlatforms() as $platform) {
        $slugsByLocale = [];
        foreach (discoverLocales() as $locale) {
            $slugs = collect(loadPlatformFile($locale, $platform))->pluck('slug')->sort()->values()->all();
            $slugsByLocale[$locale] = $slugs;
        }

        $reference = reset($slugsByLocale);
        foreach ($slugsByLocale as $locale => $slugs) {
            expect($slugs)->toEqual(
                $reference,
                "Locale {$locale} has different slugs for platform {$platform} than the source locale",
            );
        }
    }
});

it('preserves structural fields across locales for each slug', function () {
    $structural = ['category', 'image_count'];

    foreach (discoverPlatforms() as $platform) {
        $byLocale = [];
        foreach (discoverLocales() as $locale) {
            foreach (loadPlatformFile($locale, $platform) as $row) {
                $byLocale[$row['slug']][$locale] = $row;
            }
        }

        foreach ($byLocale as $slug => $perLocale) {
            foreach ($structural as $field) {
                $values = array_unique(array_column($perLocale, $field));
                expect($values)->toHaveCount(
                    1,
                    "Slug {$slug} has divergent {$field} across locales: ".json_encode($values),
                );
            }

            // Same number of slides
            $slideCounts = array_unique(array_map(
                fn ($t) => $t['slides'] === null ? 0 : count($t['slides']),
                $perLocale,
            ));
            expect($slideCounts)->toHaveCount(
                1,
                "Slug {$slug} has divergent slide count across locales",
            );

            // image_keywords (top-level + per-slide) must be EN-only — identical across locales
            $topLevelKeywords = array_unique(array_map(
                fn ($t) => json_encode($t['image_keywords']),
                $perLocale,
            ));
            expect($topLevelKeywords)->toHaveCount(
                1,
                "Slug {$slug} top-level image_keywords drifted across locales (must stay EN)",
            );

            $first = reset($perLocale);
            $slidesCount = $first['slides'] === null ? 0 : count($first['slides']);
            for ($i = 0; $i < $slidesCount; $i++) {
                $perSlideKeywords = array_unique(array_map(
                    fn ($t) => json_encode(data_get($t, "slides.{$i}.image_keywords")),
                    $perLocale,
                ));
                expect($perSlideKeywords)->toHaveCount(
                    1,
                    "Slug {$slug} slide {$i} image_keywords drifted across locales (must stay EN)",
                );
            }
        }
    }
});

it('has no duplicate slugs within a single (locale, platform) file', function () {
    foreach (discoverLocales() as $locale) {
        foreach (discoverPlatforms() as $platform) {
            $slugs = collect(loadPlatformFile($locale, $platform))->pluck('slug');
            expect($slugs->count())->toBe(
                $slugs->unique()->count(),
                "Duplicate slugs in templates/{$locale}/{$platform}.php",
            );
        }
    }
});

it('uses only known categories', function () {
    foreach (discoverLocales() as $locale) {
        foreach (discoverPlatforms() as $platform) {
            foreach (loadPlatformFile($locale, $platform) as $row) {
                expect(in_array($row['category'], KNOWN_CATEGORIES, true))->toBeTrue(
                    "Slug {$row['slug']} in templates/{$locale}/{$platform}.php uses unknown category '{$row['category']}'",
                );
            }
        }
    }
});

it('has every required field on every template', function () {
    $required = ['slug', 'category', 'name', 'content', 'image_count'];
    $optional = ['description', 'image_keywords', 'slides'];
    $expected = array_merge($required, $optional);

    foreach (discoverLocales() as $locale) {
        foreach (discoverPlatforms() as $platform) {
            foreach (loadPlatformFile($locale, $platform) as $i => $row) {
                foreach ($required as $field) {
                    expect(array_key_exists($field, $row))->toBeTrue(
                        "templates/{$locale}/{$platform}.php row {$i} missing required field '{$field}'",
                    );
                }
                foreach ($optional as $field) {
                    expect(array_key_exists($field, $row))->toBeTrue(
                        "templates/{$locale}/{$platform}.php row {$i} missing optional field '{$field}' (use null when unused)",
                    );
                }
            }
        }
    }
});
