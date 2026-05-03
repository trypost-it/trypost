<?php

declare(strict_types=1);

namespace App\Console\Commands\PostTemplates;

use App\Services\PostTemplate\Registry;
use Illuminate\Console\Command;

class Report extends Command
{
    protected $signature = 'templates:report';

    protected $description = 'Report post-template translation status across all locales.';

    public function handle(Registry $registry): int
    {
        $locales = $registry->allLocales()->all();
        $platforms = $registry->allPlatforms()->all();
        $defaultLocale = Registry::DEFAULT_LOCALE;

        if (! in_array($defaultLocale, $locales, true)) {
            $this->error("Default locale '{$defaultLocale}' folder not found at templates/{$defaultLocale}/");

            return self::FAILURE;
        }

        $this->info('Post Template Status');
        $this->line('Locales: '.implode(', ', $locales));
        $this->line('Source: '.$defaultLocale);
        $this->newLine();

        $totalTemplates = 0;
        $totalDrift = 0;

        foreach ($platforms as $platform) {
            $this->line("<comment>Platform: {$platform}</comment>");

            $sourceTemplates = $this->loadFile($defaultLocale, $platform);
            $sourceCount = count($sourceTemplates);
            $sourceSlugs = collect($sourceTemplates)->pluck('slug')->all();
            $totalTemplates += $sourceCount;

            foreach ($locales as $locale) {
                $rows = $this->loadFile($locale, $platform);
                $count = count($rows);
                $localeSlugs = collect($rows)->pluck('slug')->all();
                $missing = array_diff($sourceSlugs, $localeSlugs);
                $extra = array_diff($localeSlugs, $sourceSlugs);

                if ($locale === $defaultLocale) {
                    $this->line("  {$locale}: {$count} templates");

                    continue;
                }

                if ($count === $sourceCount && empty($missing) && empty($extra)) {
                    $this->line("  {$locale}: {$count} templates  <info>✓</info>");

                    continue;
                }

                $this->line("  {$locale}: {$count} templates  <error>⚠</error>");

                if ($missing) {
                    $totalDrift += count($missing);
                    $this->line('    missing: '.implode(', ', $missing));
                }

                if ($extra) {
                    $totalDrift += count($extra);
                    $this->line('    extra (not in source): '.implode(', ', $extra));
                }
            }

            $this->newLine();
        }

        $this->info("Total source templates: {$totalTemplates} across ".count($platforms).' platforms × '.count($locales).' locales');

        if ($totalDrift > 0) {
            $this->warn("{$totalDrift} translation gaps found. Run `php artisan test --filter=PostTemplates/Integrity` for full diagnosis.");

            return self::SUCCESS;
        }

        $this->info('All locales are in sync with the source.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadFile(string $locale, string $platform): array
    {
        $path = base_path("templates/{$locale}/{$platform}.php");

        return is_file($path) ? require $path : [];
    }
}
