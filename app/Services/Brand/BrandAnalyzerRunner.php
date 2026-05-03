<?php

declare(strict_types=1);

namespace App\Services\Brand;

use App\Ai\Agents\BrandAnalyzer;
use App\Models\Workspace;
use App\Services\Ai\RecordAiUsage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use League\HTMLToMarkdown\HtmlConverter;
use Throwable;

final class BrandAnalyzerRunner
{
    private const int MARKDOWN_MAX_CHARS = 4000;

    public function isAvailable(): bool
    {
        return match (config('ai.default')) {
            'openai' => ! empty(config('services.openai.api_key')),
            'gemini' => ! empty(config('services.gemini.api_key')),
            default => false,
        };
    }

    public function analyze(string $bodyHtml, ?Workspace $workspace = null, ?string $userId = null): ?LlmBrandAnalysis
    {
        $markdown = $this->htmlToMarkdown($bodyHtml);

        if ($markdown === '') {
            return null;
        }

        try {
            $response = (new BrandAnalyzer)->prompt($markdown);
        } catch (Throwable $e) {
            Log::warning('BrandAnalyzer failed, falling back to meta tags', ['error' => $e->getMessage()]);

            return null;
        }

        // Brand analysis can run during onboarding before the user has a
        // workspace; skip usage tracking in that case (the cost is small and
        // one-shot). Other AI flows always carry a workspace.
        if ($workspace !== null) {
            RecordAiUsage::recordText(
                workspace: $workspace,
                promptTokens: $response->usage->promptTokens,
                completionTokens: $response->usage->completionTokens,
                provider: (string) config('ai.default'),
                model: (string) config('ai.default_text_model'),
                userId: $userId,
                metadata: ['agent' => 'brand_analyzer'],
            );
        }

        return LlmBrandAnalysis::fromResponse($response);
    }

    private function htmlToMarkdown(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $markdown = (new HtmlConverter(['strip_tags' => true]))->convert($html);

        return Str::limit(trim($markdown), self::MARKDOWN_MAX_CHARS, '');
    }
}
