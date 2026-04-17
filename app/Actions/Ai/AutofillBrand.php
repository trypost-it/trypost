<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Services\Brand\BrandAnalyzerRunner;
use App\Services\Brand\BrandMetadata;
use App\Services\Brand\HomepageMetaExtractor;
use App\Services\Brand\SafeHttpFetcher;

final class AutofillBrand
{
    public function __construct(
        private readonly SafeHttpFetcher $fetcher,
        private readonly HomepageMetaExtractor $extractor,
        private readonly BrandAnalyzerRunner $analyzer,
    ) {}

    public function __invoke(string $url): BrandMetadata
    {
        $url = $this->fetcher->normalizeUrl($url);

        $html = $this->fetcher->get($url)->body();

        $metadata = $this->extractor->extract($html, $url);

        if (! $this->analyzer->isAvailable()) {
            return $metadata;
        }

        $analysis = $this->analyzer->analyze($this->extractor->extractBodyHtml($html));

        return $analysis === null
            ? $metadata
            : $metadata->mergeLlm($analysis);
    }
}
