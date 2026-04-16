<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Models\Workspace;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

class AutofillBrand
{
    private const REQUEST_TIMEOUT_SECONDS = 10;

    private const MAX_LOGO_BYTES = 2 * 1024 * 1024;

    private const ALLOWED_LOGO_MIME = ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon'];

    /**
     * @return array{name: ?string, brand_description: ?string, content_language: ?string, logo_url: ?string}
     */
    public function __invoke(string $url, Workspace $workspace): array
    {
        $url = $this->normalizeUrl($url);
        $this->guardAgainstSsrf($url);

        $html = $this->fetchHtml($url);
        $crawler = new Crawler($html, $url);

        $logoUrl = $this->extractLogoUrl($crawler, $url);

        if ($logoUrl) {
            $this->attachLogoToWorkspace($workspace, $logoUrl);
        }

        return [
            'name' => $this->extractName($crawler),
            'brand_description' => $this->extractDescription($crawler),
            'content_language' => $this->extractLanguage($crawler),
            'logo_url' => $logoUrl,
        ];
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
            return $url;
        }

        return 'https://'.$url;
    }

    private function guardAgainstSsrf(string $url): void
    {
        $parts = parse_url($url);

        if (! $parts || ! in_array(strtolower(data_get($parts, 'scheme', '')), ['http', 'https'], true)) {
            throw new RuntimeException('Only http:// and https:// URLs are supported.');
        }

        $host = data_get($parts, 'host');

        if (! $host) {
            throw new RuntimeException('URL is missing a host.');
        }

        $ip = gethostbyname($host);

        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            throw new RuntimeException("Could not resolve host: {$host}");
        }

        $isPublic = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        if (! $isPublic) {
            throw new RuntimeException('Internal or private network addresses are not allowed.');
        }
    }

    private function fetchHtml(string $url): string
    {
        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->withUserAgent('TryPostBot/1.0 (+https://trypost.it)')
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->get($url);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Could not reach the website: {$e->getMessage()}");
        }

        if ($response->failed()) {
            throw new RuntimeException("Website returned HTTP {$response->status()}.");
        }

        return $response->body();
    }

    private function extractName(Crawler $crawler): ?string
    {
        $ogSiteName = $this->metaContent($crawler, 'property', 'og:site_name');
        if ($ogSiteName) {
            return $ogSiteName;
        }

        $ogTitle = $this->metaContent($crawler, 'property', 'og:title');
        if ($ogTitle) {
            return $this->stripTitleSuffix($ogTitle);
        }

        $title = $crawler->filter('title')->first();
        if ($title->count() > 0) {
            return $this->stripTitleSuffix(trim($title->text(''))) ?: null;
        }

        return null;
    }

    private function extractDescription(Crawler $crawler): ?string
    {
        return $this->metaContent($crawler, 'name', 'description')
            ?? $this->metaContent($crawler, 'property', 'og:description');
    }

    private function extractLanguage(Crawler $crawler): ?string
    {
        $html = $crawler->filter('html')->first();

        if ($html->count() === 0) {
            return null;
        }

        $lang = trim((string) $html->attr('lang', ''));

        if ($lang === '') {
            return null;
        }

        return $this->normalizeLanguageCode($lang);
    }

    private function normalizeLanguageCode(string $raw): ?string
    {
        $lower = strtolower($raw);

        return match (true) {
            str_starts_with($lower, 'pt') => 'pt-BR',
            str_starts_with($lower, 'es') => 'es',
            str_starts_with($lower, 'en') => 'en',
            default => null,
        };
    }

    private function extractLogoUrl(Crawler $crawler, string $baseUrl): ?string
    {
        $candidates = [];

        foreach ($crawler->filter('link[rel="apple-touch-icon"]') as $node) {
            $href = $node->getAttribute('href');
            if ($href) {
                $candidates[] = ['href' => $href, 'priority' => 100, 'size' => $this->parseIconSize($node->getAttribute('sizes'))];
            }
        }

        foreach ($crawler->filter('link[rel*="icon"]') as $node) {
            $href = $node->getAttribute('href');
            if ($href) {
                $candidates[] = ['href' => $href, 'priority' => 50, 'size' => $this->parseIconSize($node->getAttribute('sizes'))];
            }
        }

        $ogImage = $this->metaContent($crawler, 'property', 'og:image');
        if ($ogImage) {
            $candidates[] = ['href' => $ogImage, 'priority' => 25, 'size' => 0];
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['priority'] <=> $a['priority'] ?: $b['size'] <=> $a['size']);

        return $this->resolveUrl($candidates[0]['href'], $baseUrl);
    }

    private function parseIconSize(?string $sizes): int
    {
        if (! $sizes) {
            return 0;
        }

        preg_match_all('/(\d+)x\d+/', $sizes, $matches);

        return $matches[1] === [] ? 0 : max(array_map('intval', $matches[1]));
    }

    private function resolveUrl(string $href, string $baseUrl): string
    {
        if (preg_match('~^https?://~i', $href)) {
            return $href;
        }

        $base = parse_url($baseUrl);
        $scheme = data_get($base, 'scheme', 'https');
        $host = data_get($base, 'host');

        if (str_starts_with($href, '//')) {
            return "{$scheme}:{$href}";
        }

        if (str_starts_with($href, '/')) {
            return "{$scheme}://{$host}{$href}";
        }

        return "{$scheme}://{$host}/{$href}";
    }

    private function attachLogoToWorkspace(Workspace $workspace, string $logoUrl): void
    {
        $this->guardAgainstSsrf($logoUrl);

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->withUserAgent('TryPostBot/1.0 (+https://trypost.it)')
                ->get($logoUrl);
        } catch (ConnectionException) {
            return;
        }

        if ($response->failed()) {
            return;
        }

        $contentType = strtolower(explode(';', (string) $response->header('Content-Type'))[0]);

        if (! in_array($contentType, self::ALLOWED_LOGO_MIME, true)) {
            return;
        }

        $body = $response->body();

        if (strlen($body) > self::MAX_LOGO_BYTES) {
            return;
        }

        $extension = $this->extensionForMime($contentType);
        $tempPath = tempnam(sys_get_temp_dir(), 'logo_').'.'.$extension;
        file_put_contents($tempPath, $body);

        try {
            $workspace->clearMediaCollection('logo');
            $workspace->addMediaFromPath($tempPath, 'logo.'.$extension, 'logo', ['ai_autofill' => true]);
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/x-icon', 'image/vnd.microsoft.icon' => 'ico',
            default => 'png',
        };
    }

    private function metaContent(Crawler $crawler, string $attr, string $value): ?string
    {
        $node = $crawler->filter("meta[{$attr}=\"{$value}\"]")->first();

        if ($node->count() === 0) {
            return null;
        }

        $content = trim((string) $node->attr('content', ''));

        return $content === '' ? null : $content;
    }

    private function stripTitleSuffix(string $title): string
    {
        foreach ([' | ', ' - ', ' — ', ' – '] as $sep) {
            $idx = mb_strpos($title, $sep);
            if ($idx !== false && $idx > 0) {
                return trim(mb_substr($title, 0, $idx));
            }
        }

        return trim($title);
    }
}
