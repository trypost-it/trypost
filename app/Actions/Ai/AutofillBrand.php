<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Services\Brand\BrandAnalyzerRunner;
use App\Services\Brand\BrandMetadata;
use App\Services\Brand\HomepageMetaExtractor;
use App\Services\Brand\LogoColorExtractor;
use App\Services\Brand\SafeHttpFetcher;

final class AutofillBrand
{
    public function __construct(
        private readonly SafeHttpFetcher $fetcher,
        private readonly HomepageMetaExtractor $extractor,
        private readonly BrandAnalyzerRunner $analyzer,
        private readonly LogoColorExtractor $logoColors,
    ) {}

    public function __invoke(string $url): BrandMetadata
    {
        $url = $this->fetcher->normalizeUrl($url);

        $html = $this->fetcher->get($url)->body();

        // Pull up to 3 external stylesheets so the deterministic color
        // extractor can find brand/background/text colors that aren't inlined.
        $extraCss = '';
        $stylesheetUrls = array_slice($this->extractor->extractStylesheetUrls($html, $url), 0, 3);
        foreach ($stylesheetUrls as $cssUrl) {
            $response = $this->fetcher->tryGet($cssUrl);
            if ($response !== null && $response->successful()) {
                $extraCss .= "\n".$response->body();
            }
        }

        $metadata = $this->extractor->extract($html, $url, $extraCss);

        // If the deterministic CSS scan didn't yield a brand color, pull the
        // dominant non-neutral color out of the logo image itself. This works
        // even when the site uses utility CSS (Tailwind, etc.) where no
        // semantic --primary var or body { background } rule exists.
        if ($metadata->brandColor === null && $metadata->logoUrl !== null) {
            $logoColor = $this->logoColors->extractFromUrl($metadata->logoUrl);
            if ($logoColor !== null) {
                $metadata = $metadata->withBrandColor($logoColor);
            }
        }

        if (! $this->analyzer->isAvailable()) {
            return $metadata;
        }

        $analysis = $this->analyzer->analyze($this->extractor->extractBodyHtml($html));

        return $analysis === null
            ? $metadata
            : $metadata->mergeLlm($analysis);
    }
}
