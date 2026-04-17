<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Ai\Agents\Humanizer;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;
use Throwable;

class HumanizerService
{
    public function humanize(string $text, Workspace $workspace): string
    {
        if (trim($text) === '') {
            return $text;
        }

        $instructions = view('prompts.assistant.humanize', [
            'brand_name' => $workspace->name,
            'brand_tone' => $workspace->brand_tone,
            'brand_voice_notes' => $workspace->brand_voice_notes,
            'content_language' => $workspace->content_language,
        ])->render();

        try {
            $response = (new Humanizer($instructions))->prompt($text);

            $rewritten = trim((string) $response->text);

            return $rewritten !== '' ? $rewritten : $text;
        } catch (Throwable $e) {
            Log::warning('Humanizer pass failed; returning original text.', [
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);

            return $text;
        }
    }
}
