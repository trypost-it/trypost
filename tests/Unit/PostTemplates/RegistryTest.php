<?php

declare(strict_types=1);

use App\Services\PostTemplate\PostTemplateData;
use App\Services\PostTemplate\Registry;
use App\Services\PostTemplate\TemplateNotFoundException;

beforeEach(fn () => $this->registry = new Registry);

test('all() returns every template for the default locale', function () {
    $all = $this->registry->all('en');

    expect($all)->not->toBeEmpty();
    expect($all->first())->toBeInstanceOf(PostTemplateData::class);
});

test('all() with platform filter only returns templates for that platform', function () {
    $carousels = $this->registry->all('en', 'instagram_carousel');

    expect($carousels)->not->toBeEmpty();
    expect($carousels->every(fn ($t) => $t->platform === 'instagram_carousel'))->toBeTrue();
});

test('find() returns the requested template by slug', function () {
    $template = $this->registry->find('feature_launch_carousel', 'en');

    expect($template)->toBeInstanceOf(PostTemplateData::class);
    expect($template->slug)->toBe('feature_launch_carousel');
    expect($template->platform)->toBe('instagram_carousel');
});

test('find() throws when slug does not exist', function () {
    $this->registry->find('this-slug-does-not-exist', 'en');
})->throws(TemplateNotFoundException::class);

test('all() falls back to default locale when locale folder is missing', function () {
    $missing = $this->registry->all('xx-XX');
    $en = $this->registry->all('en');

    expect($missing->count())->toBe($en->count());
});

test('find() falls back to default locale when slug missing in requested locale', function () {
    // Same fallback behavior at single-template granularity.
    $template = $this->registry->find('feature_launch_carousel', 'xx-XX');

    expect($template->slug)->toBe('feature_launch_carousel');
});

test('paginate() returns LengthAwarePaginator with the requested page slice', function () {
    $paginator = $this->registry->paginate(locale: 'en', perPage: 5, page: 1);

    expect($paginator->perPage())->toBe(5);
    expect($paginator->currentPage())->toBe(1);
    expect($paginator->items())->toHaveCount(5);
});

test('paginate() filters by search across name and description', function () {
    // "carousel" appears in many template names — should narrow but not eliminate
    $results = $this->registry->paginate(locale: 'en', search: 'carousel', perPage: 100);

    expect($results->total())->toBeGreaterThan(0);
    foreach ($results->items() as $template) {
        $matches = stripos($template->name, 'carousel') !== false
            || stripos((string) $template->description, 'carousel') !== false;
        expect($matches)->toBeTrue();
    }
});

test('paginate() with platform filter respects pagination', function () {
    $page1 = $this->registry->paginate(locale: 'en', platform: 'instagram_carousel', perPage: 2, page: 1);
    $page2 = $this->registry->paginate(locale: 'en', platform: 'instagram_carousel', perPage: 2, page: 2);

    expect($page1->items())->toHaveCount(2);
    expect($page1->currentPage())->toBe(1);
    expect($page2->currentPage())->toBe(2);

    $page1Slugs = collect($page1->items())->pluck('slug')->all();
    $page2Slugs = collect($page2->items())->pluck('slug')->all();
    expect(array_intersect($page1Slugs, $page2Slugs))->toBeEmpty();
});

test('allSlugs() returns unique slugs from default locale', function () {
    $slugs = $this->registry->allSlugs();

    expect($slugs)->not->toBeEmpty();
    expect($slugs->count())->toBe($slugs->unique()->count());
});

test('allPlatforms() returns every platform filename', function () {
    $platforms = $this->registry->allPlatforms();

    expect($platforms)->toContain('instagram_carousel');
    expect($platforms)->toContain('linkedin_post');
});

test('allLocales() returns every locale subdirectory', function () {
    $locales = $this->registry->allLocales();

    expect($locales)->toContain('en');
});
