<?php

declare(strict_types=1);

use App\Actions\Ai\AutofillBrand;
use App\Ai\Agents\BrandAnalyzer;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Run tests without LLM credentials so the deterministic fallback is exercised.
    config()->set('services.gemini.api_key', '');
    config()->set('services.openai.api_key', '');

    $this->autofill = fn (string $url) => app(AutofillBrand::class)($url);
});

test('extracts name, description, language, and logo from meta tags', function () {
    Http::fake([
        'example.com' => Http::response(<<<'HTML'
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head>
              <title>Acme Coffee | The best beans online</title>
              <meta name="description" content="Premium artisan coffee beans shipped worldwide.">
              <meta property="og:site_name" content="Acme Coffee">
              <meta property="og:image" content="https://example.com/og.png">
              <link rel="apple-touch-icon" href="https://example.com/apple-touch-icon.png">
              <link rel="icon" sizes="512x512" href="/icon-512.png">
            </head>
            <body>Welcome</body>
            </html>
        HTML, 200),
        'example.com/icon-512.png' => Http::response(file_get_contents(__DIR__.'/../../fixtures/1x1.png'), 200, ['Content-Type' => 'image/png']),
    ]);

    $result = ($this->autofill)('https://example.com');

    expect($result->name)->toBe('Acme Coffee');
    expect($result->description)->toBe('Premium artisan coffee beans shipped worldwide.');
    expect($result->language)->toBe('pt-BR');
    // The 512x512 PNG favicon wins over the unsized apple-touch-icon; og:image is ignored.
    expect($result->logoUrl)->toBe('https://example.com/icon-512.png');
});

test('falls back to /favicon.ico when no icon link is declared', function () {
    Http::fake([
        'example.com' => Http::response('<html><head><title>Foo</title></head></html>', 200),
    ]);

    $result = ($this->autofill)('https://example.com');

    expect($result->logoUrl)->toBe('https://example.com/favicon.ico');
});

test('ignores og:image when no icon links are present', function () {
    Http::fake([
        'example.com/page' => Http::response(<<<'HTML'
            <html>
            <head>
              <title>Marketing</title>
              <meta property="og:image" content="https://example.com/social-card.png">
            </head>
            </html>
        HTML, 200),
    ]);

    $result = ($this->autofill)('https://example.com/page');

    // og:image is NOT used — falls back to /favicon.ico at the origin instead.
    expect($result->logoUrl)->toBe('https://example.com/favicon.ico');
});

test('falls back to title without og:site_name', function () {
    Http::fake([
        'example.com' => Http::response(<<<'HTML'
            <html lang="en">
            <head>
              <title>Super SaaS | Landing page</title>
              <meta name="description" content="A simple product.">
            </head>
            <body></body>
            </html>
        HTML, 200),
    ]);

    $result = ($this->autofill)('https://example.com');

    expect($result->name)->toBe('Super SaaS');
    expect($result->language)->toBe('en');
});

test('normalizes various language codes to supported locales', function (string $lang, ?string $expected) {
    Http::fake([
        'example.com' => Http::response("<html lang=\"{$lang}\"><head><title>X</title></head></html>", 200),
    ]);

    $result = ($this->autofill)('https://example.com');

    expect($result->language)->toBe($expected);
})->with([
    ['pt', 'pt-BR'],
    ['pt-PT', 'pt-BR'],
    ['en-US', 'en'],
    ['es-MX', 'es'],
    ['fr', null],
    ['ja-JP', null],
]);

test('rejects non-http schemes', function () {
    expect(fn () => ($this->autofill)('ftp://example.com'))
        ->toThrow(RuntimeException::class);
});

test('rejects private network addresses', function () {
    expect(fn () => ($this->autofill)('http://127.0.0.1'))
        ->toThrow(RuntimeException::class);

    expect(fn () => ($this->autofill)('http://192.168.1.1'))
        ->toThrow(RuntimeException::class);
});

test('adds https:// when scheme is missing', function () {
    Http::fake([
        'example.com' => Http::response('<html><head><title>ok</title></head></html>', 200),
    ]);

    ($this->autofill)('example.com');

    Http::assertSent(fn (HttpRequest $req) => str_starts_with($req->url(), 'https://example.com'));
});

test('falls back to domain-derived name when site has no meta tags', function () {
    Http::fake([
        'example.com' => Http::response('<html><body></body></html>', 200),
    ]);

    $result = ($this->autofill)('https://example.com');

    expect($result->name)->toBe('Example');
    expect($result->description)->toBeNull();
    expect($result->language)->toBeNull();
    // logoUrl always falls back to /favicon.ico since that URL exists on most sites.
    expect($result->logoUrl)->toBe('https://example.com/favicon.ico');
});

test('falls back to domain-derived name when title is a tagline with no separator', function () {
    Http::fake([
        'sendkit.dev' => Http::response(<<<'HTML'
            <html>
            <head>
              <title>Email API, SMTP & Marketing Platform for Developers & AI Agents</title>
            </head>
            <body></body>
            </html>
        HTML, 200),
    ]);

    $result = ($this->autofill)('https://sendkit.dev');

    expect($result->name)->toBe('Sendkit');
});

test('throws when upstream site returns an error', function () {
    Http::fake([
        'example.com' => Http::response('', 500),
    ]);

    expect(fn () => ($this->autofill)('https://example.com'))
        ->toThrow(RuntimeException::class);
});

test('when llm is configured, polishes description/tone/language/voice_notes via BrandAnalyzer', function () {
    config()->set('services.gemini.api_key', 'fake-key');

    Http::fake([
        'example.com' => Http::response(<<<'HTML'
            <html lang="en">
            <head>
              <title>Widget Co</title>
              <meta name="description" content="A very terse seo blurb.">
            </head>
            <body>
              <main>
                <h1>Build widgets faster</h1>
                <p>Widget Co helps small teams ship production widgets 10x faster without writing boilerplate.</p>
              </main>
            </body>
            </html>
        HTML, 200),
    ]);

    BrandAnalyzer::fake([
        [
            'description' => 'Widget Co helps small teams ship production widgets faster.',
            'tone' => 'friendly',
            'language' => 'en',
            'voice_notes' => 'Use short punchy sentences. Focus on developer benefits.',
        ],
    ]);

    $result = ($this->autofill)('https://example.com');

    expect($result->description)->toBe('Widget Co helps small teams ship production widgets faster.');
    expect($result->tone)->toBe('friendly');
    expect($result->language)->toBe('en');
    expect($result->voiceNotes)->toBe('Use short punchy sentences. Focus on developer benefits.');
});

test('when llm is not configured, falls back to meta tags only', function () {
    // beforeEach already cleared api keys.
    Http::fake([
        'example.com' => Http::response(<<<'HTML'
            <html lang="pt-BR">
            <head>
              <title>Marca</title>
              <meta name="description" content="Uma descrição curta.">
            </head>
            <body><main><p>hello</p></main></body>
            </html>
        HTML, 200),
    ]);

    // Fail loud if BrandAnalyzer is called.
    BrandAnalyzer::fake()->preventStrayPrompts();

    $result = ($this->autofill)('https://example.com');

    expect($result->description)->toBe('Uma descrição curta.');
    expect($result->language)->toBe('pt-BR');
    expect($result->tone)->toBeNull();
    expect($result->voiceNotes)->toBeNull();
});

test('falls back to meta tags when BrandAnalyzer throws', function () {
    config()->set('services.gemini.api_key', 'fake-key');

    Http::fake([
        'example.com' => Http::response(<<<'HTML'
            <html lang="en">
            <head>
              <title>Acme</title>
              <meta name="description" content="Fallback desc.">
            </head>
            <body><main><p>hi</p></main></body>
            </html>
        HTML, 200),
    ]);

    BrandAnalyzer::fake([
        fn () => throw new RuntimeException('LLM went down'),
    ]);

    $result = ($this->autofill)('https://example.com');

    expect($result->description)->toBe('Fallback desc.');
    expect($result->language)->toBe('en');
    expect($result->tone)->toBeNull();
    expect($result->voiceNotes)->toBeNull();
});

test('BrandMetadata toArray exposes the shape the controller expects', function () {
    Http::fake([
        'example.com' => Http::response('<html lang="en"><head><title>Foo</title></head></html>', 200),
    ]);

    $result = ($this->autofill)('https://example.com');

    expect($result->toArray())->toHaveKeys([
        'name',
        'brand_description',
        'content_language',
        'brand_tone',
        'brand_voice_notes',
        'logo_url',
    ]);
});
