<?php

declare(strict_types=1);

namespace App\Services\PostTemplate;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class Registry
{
    public const string DEFAULT_LOCALE = 'en';

    /** @var array<string, Collection<int, PostTemplateData>> */
    private array $cache = [];

    /**
     * Load every template for the given locale, optionally filtered by platform.
     * Falls back to the default locale when the requested locale folder or file
     * is missing — guarantees the templates page always renders something.
     *
     * @return Collection<int, PostTemplateData>
     */
    public function all(string $locale, ?string $platform = null): Collection
    {
        $key = "{$locale}:".($platform ?? '*');

        return $this->cache[$key] ??= $this->loadFromDisk($locale, $platform);
    }

    /**
     * Find a template by slug in the given locale, falling back to the default
     * locale when the slug exists in EN but not in the requested locale.
     */
    public function find(string $slug, string $locale): PostTemplateData
    {
        $template = $this->all($locale)->firstWhere('slug', $slug);

        if ($template === null && $locale !== self::DEFAULT_LOCALE) {
            $template = $this->all(self::DEFAULT_LOCALE)->firstWhere('slug', $slug);
        }

        return $template ?? throw new TemplateNotFoundException($slug);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function paginate(
        string $locale,
        ?string $platform = null,
        ?string $search = null,
        int $perPage = 20,
        int $page = 1,
        ?string $path = null,
        array $query = [],
    ): LengthAwarePaginator {
        $templates = $this->all($locale, $platform)
            ->when($search, fn (Collection $c) => $c->filter(
                fn (PostTemplateData $t) => str_contains(mb_strtolower($t->name), mb_strtolower($search))
                    || str_contains(mb_strtolower($t->description ?? ''), mb_strtolower($search))
            ))
            ->values();

        return new LengthAwarePaginator(
            $templates->forPage($page, $perPage)->values(),
            $templates->count(),
            $perPage,
            $page,
            array_filter([
                'path' => $path,
                'query' => $query !== [] ? $query : null,
            ]),
        );
    }

    /**
     * @return Collection<int, string>
     */
    public function allSlugs(): Collection
    {
        return $this->all(self::DEFAULT_LOCALE)
            ->pluck('slug')
            ->unique()
            ->values();
    }

    /**
     * Filenames (without `.php`) under the default locale folder.
     *
     * @return Collection<int, string>
     */
    public function allPlatforms(): Collection
    {
        $files = glob($this->localePath(self::DEFAULT_LOCALE).'/*.php') ?: [];

        return collect($files)
            ->map(fn (string $f) => basename($f, '.php'))
            ->values();
    }

    /**
     * Subdirectory names under templates/.
     *
     * @return Collection<int, string>
     */
    public function allLocales(): Collection
    {
        $dirs = glob(base_path('templates').'/*', GLOB_ONLYDIR) ?: [];

        return collect($dirs)
            ->map(fn (string $d) => basename($d))
            ->values();
    }

    /**
     * Forget the in-memory cache. Used by tests; not needed at runtime.
     */
    public function flush(): void
    {
        $this->cache = [];
    }

    /**
     * @return Collection<int, PostTemplateData>
     */
    private function loadFromDisk(string $locale, ?string $platform): Collection
    {
        $localePath = $this->localePath($locale);

        if (! is_dir($localePath)) {
            return $locale === self::DEFAULT_LOCALE
                ? collect()
                : $this->loadFromDisk(self::DEFAULT_LOCALE, $platform);
        }

        $files = $platform !== null
            ? array_filter([$localePath."/{$platform}.php"], 'is_file')
            : (glob($localePath.'/*.php') ?: []);

        if (empty($files) && $locale !== self::DEFAULT_LOCALE) {
            return $this->loadFromDisk(self::DEFAULT_LOCALE, $platform);
        }

        return collect($files)
            ->flatMap(function (string $file): array {
                $platformName = basename($file, '.php');
                $rows = require $file;

                if (! is_array($rows)) {
                    return [];
                }

                return array_map(
                    fn (array $row) => PostTemplateData::fromArray($row, $platformName),
                    $rows,
                );
            })
            ->values();
    }

    private function localePath(string $locale): string
    {
        return base_path("templates/{$locale}");
    }
}
