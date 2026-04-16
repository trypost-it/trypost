<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\Ai\Intent;

class IntentDetector
{
    public function detect(string $prompt): Intent
    {
        $lower = mb_strtolower($prompt);

        if ($this->isProhibited($lower)) {
            return Intent::Blocked;
        }

        $videoKeywords = ['video', 'clip', 'reel', 'animation', 'animate', 'footage'];
        $imageKeywords = ['image', 'photo', 'picture', 'illustration', 'draw', 'design', 'visual', 'graphic'];
        $audioKeywords = ['audio', 'voice', 'narrate', 'speak', 'tts', 'voiceover', 'text to speech'];

        foreach ($videoKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return Intent::Video;
            }
        }

        foreach ($imageKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return Intent::Image;
            }
        }

        foreach ($audioKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return Intent::Audio;
            }
        }

        return Intent::Text;
    }

    private function isProhibited(string $lower): bool
    {
        $prohibited = [
            'porn', 'xxx', 'nude', 'naked', 'hentai', 'nsfw',
            'cocaine', 'heroin', 'meth',
            'murder', 'suicide', 'self-harm', 'self harm',
            'pedophil', 'child porn', 'underage',
            'terrorist', 'terrorism',
            'racist', 'racism', 'nazi', 'white supremac',
            'gore', 'torture', 'dismember',
        ];

        foreach ($prohibited as $word) {
            if (preg_match('/\b'.preg_quote($word, '/').'/i', $lower)) {
                return true;
            }
        }

        return false;
    }
}
