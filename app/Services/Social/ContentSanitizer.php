<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\SocialAccount\Platform;

class ContentSanitizer
{
    public function sanitize(string $content, Platform $platform): string
    {
        return match ($platform) {
            Platform::LinkedIn, Platform::LinkedInPage => $this->convertBoldAndStrip($content),
            default => $this->stripHtml($content),
        };
    }

    private function stripHtml(string $content): string
    {
        // Convert <p> tags to newlines
        $content = preg_replace('/<p[^>]*>/i', '', $content);
        $content = str_replace('</p>', "\n", $content);

        // Convert <br> to newlines
        $content = preg_replace('/<br\s*\/?>/i', "\n", $content);

        // Convert list items to dash prefix
        $content = preg_replace('/<li[^>]*>/i', '- ', $content);
        $content = str_replace('</li>', "\n", $content);

        // Strip remaining HTML tags
        $content = strip_tags($content);

        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Clean up excessive newlines (max 2 consecutive)
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        return trim($content);
    }

    private function convertBoldAndStrip(string $content): string
    {
        // Convert <strong>/<b> to Unicode bold characters for LinkedIn
        $content = preg_replace_callback(
            '/<(?:strong|b)>(.*?)<\/(?:strong|b)>/si',
            fn ($matches) => $this->toUnicodeBold($matches[1]),
            $content
        );

        // Convert <u> to Unicode underline
        $content = preg_replace_callback(
            '/<u>(.*?)<\/u>/si',
            fn ($matches) => $this->toUnicodeUnderline($matches[1]),
            $content
        );

        return $this->stripHtml($content);
    }

    private function toUnicodeBold(string $text): string
    {
        $map = [
            'a' => '𝗮', 'b' => '𝗯', 'c' => '𝗰', 'd' => '𝗱', 'e' => '𝗲',
            'f' => '𝗳', 'g' => '𝗴', 'h' => '𝗵', 'i' => '𝗶', 'j' => '𝗷',
            'k' => '𝗸', 'l' => '𝗹', 'm' => '𝗺', 'n' => '𝗻', 'o' => '𝗼',
            'p' => '𝗽', 'q' => '𝗾', 'r' => '𝗿', 's' => '𝘀', 't' => '𝘁',
            'u' => '𝘂', 'v' => '𝘃', 'w' => '𝘄', 'x' => '𝘅', 'y' => '𝘆', 'z' => '𝘇',
            'A' => '𝗔', 'B' => '𝗕', 'C' => '𝗖', 'D' => '𝗗', 'E' => '𝗘',
            'F' => '𝗙', 'G' => '𝗚', 'H' => '𝗛', 'I' => '𝗜', 'J' => '𝗝',
            'K' => '𝗞', 'L' => '𝗟', 'M' => '𝗠', 'N' => '𝗡', 'O' => '𝗢',
            'P' => '𝗣', 'Q' => '𝗤', 'R' => '𝗥', 'S' => '𝗦', 'T' => '𝗧',
            'U' => '𝗨', 'V' => '𝗩', 'W' => '𝗪', 'X' => '𝗫', 'Y' => '𝗬', 'Z' => '𝗭',
            '0' => '𝟬', '1' => '𝟭', '2' => '𝟮', '3' => '𝟯', '4' => '𝟰',
            '5' => '𝟱', '6' => '𝟲', '7' => '𝟳', '8' => '𝟴', '9' => '𝟵',
        ];

        return strtr($text, $map);
    }

    private function toUnicodeUnderline(string $text): string
    {
        // Unicode combining underline character
        return implode('', array_map(
            fn ($char) => $char."\u{0332}",
            mb_str_split($text)
        ));
    }
}
