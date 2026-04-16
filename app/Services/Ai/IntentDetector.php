<?php

declare(strict_types=1);

namespace App\Services\Ai;

class IntentDetector
{
    public function detect(string $prompt): string
    {
        $lower = mb_strtolower($prompt);

        $videoKeywords = ['video', 'clip', 'reel', 'animation', 'animate', 'footage'];
        $imageKeywords = ['image', 'photo', 'picture', 'illustration', 'draw', 'design', 'visual', 'graphic'];
        $audioKeywords = ['audio', 'voice', 'narrate', 'speak', 'tts', 'voiceover', 'text to speech'];

        foreach ($videoKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return 'video';
            }
        }

        foreach ($imageKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return 'image';
            }
        }

        foreach ($audioKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return 'audio';
            }
        }

        return 'text';
    }
}
