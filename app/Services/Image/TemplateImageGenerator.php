<?php

declare(strict_types=1);

namespace App\Services\Image;

use App\Enums\Workspace\ImageStyle;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Ai\AiImageClient;
use App\Services\Ai\RecordAiUsage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\FontFactory;

class TemplateImageGenerator
{
    public const DEFAULT_WIDTH = 1080;

    public const DEFAULT_HEIGHT = 1350;

    /** Active canvas width. Set per render call so templates can scale. */
    private int $width = self::DEFAULT_WIDTH;

    /** Active canvas height. Set per render call so templates can scale. */
    private int $height = self::DEFAULT_HEIGHT;

    public function __construct(
        private BrandColorMapper $colorMapper,
        private AiImageClient $aiImage,
    ) {}

    /**
     * Render a slide using Template A (full bleed) or Template B (photo card).
     *
     * @param  array<int, string>  $imageKeywords
     * @return RenderedSlide|null The storage path + source meta, or null on failure.
     */
    public function render(
        Workspace $workspace,
        SocialAccount $socialAccount,
        string $title,
        string $body,
        array $imageKeywords,
        int $width = self::DEFAULT_WIDTH,
        int $height = self::DEFAULT_HEIGHT,
    ): ?RenderedSlide {
        $this->width = $width;
        $this->height = $height;

        $orientation = $this->orientationForCanvas();
        $rawStyle = $workspace->image_style;
        $imageStyle = match (true) {
            $rawStyle instanceof ImageStyle => $rawStyle,
            is_string($rawStyle) => ImageStyle::tryFrom($rawStyle) ?? ImageStyle::DEFAULT,
            default => ImageStyle::DEFAULT,
        };
        $language = $workspace->content_language ?? 'en';

        $imageData = $this->aiImage->generate(
            keywords: $imageKeywords,
            style: $imageStyle,
            orientation: $orientation,
            language: $language,
            brandColor: $workspace->brand_color,
            brandDescription: $workspace->brand_description,
        );
        if ($imageData === null) {
            return null;
        }

        $manager = new ImageManager(Driver::class);

        $canvas = $this->renderTemplateA($manager, $imageData, $title, $body);
        $canvas = $this->renderFooter($canvas, $socialAccount);

        $filename = 'ai-images/'.uniqid('slide_', true).'.webp';
        Storage::put($filename, (string) $canvas->encode(new WebpEncoder(quality: 85)));

        RecordAiUsage::recordImage(
            workspace: $workspace,
            provider: 'openai',
            model: AiImageClient::MODEL,
            metadata: [
                'image_style' => $imageStyle->value,
                'width' => $this->width,
                'height' => $this->height,
            ],
        );

        RecordAiUsage::recordTemplate(
            workspace: $workspace,
            provider: 'internal',
            metadata: [
                'width' => $this->width,
                'height' => $this->height,
            ],
        );

        return new RenderedSlide(
            path: $filename,
            sourceMeta: [
                'keywords' => array_values($imageKeywords),
                'style' => $imageStyle->value,
                'language' => $language,
                'model' => AiImageClient::MODEL,
                'title' => $title,
                'body' => $body,
                'width' => $this->width,
                'height' => $this->height,
            ],
        );
    }

    /**
     * Pick the closest aspect ratio for the active canvas so the AI image
     * generator returns a photo that doesn't need heavy cropping.
     */
    private function orientationForCanvas(): string
    {
        $ratio = $this->width / $this->height;
        if ($ratio > 1.1) {
            return 'landscape';
        }
        if ($ratio < 0.9) {
            return 'portrait';
        }

        return 'squarish';
    }

    private function renderTemplateA(ImageManager $manager, string $imageData, string $title, string $body): ImageInterface
    {
        // Cover-fit Unsplash image to active canvas size.
        $image = $manager->decodeBinary($imageData)->cover($this->width, $this->height);

        // Smooth gradient mask: covers full image height, peaks at 0.9 alpha (linear).
        $this->applyBottomGradient($image, 1.0, 0.9, 1.0);

        $fontBold = $this->fontPath('Inter-Bold.ttf');
        $fontMedium = $this->fontPath('Inter-Medium.ttf');

        // Layout (bottom-up): footer area → body → title. All text rendered via raw GD
        // for pixel-precise positioning. Same wrap+measure helper used for layout math.
        $titleSize = 56;
        $bodySize = 28;
        $titleLineHeight = 1.25;
        $bodyLineHeight = 1.55;
        $footerReserved = 150;
        $bodyMargin = 16;
        $titleMargin = 36;
        $padding = 60;
        $maxWidth = $this->width - 2 * $padding;

        $bodyLines = $fontMedium ? $this->wrapText($body, $fontMedium, $bodySize, $maxWidth) : [];
        $titleLines = $fontBold ? $this->wrapText($title, $fontBold, $titleSize, $maxWidth) : [];

        $bodyHeight = $this->measureBlockHeight($bodyLines, $bodySize, $bodyLineHeight);
        $titleHeight = $this->measureBlockHeight($titleLines, $titleSize, $titleLineHeight);

        $bodyTopY = $this->height - $footerReserved - $bodyMargin - $bodyHeight;
        $titleTopY = $bodyTopY - $titleMargin - $titleHeight;

        $core = $image->core()->native();

        if ($fontBold && $titleLines) {
            $this->renderTextLines($core, $titleLines, $fontBold, $titleSize, $titleLineHeight, '#ffffff', $padding, $titleTopY);
        }
        if ($fontMedium && $bodyLines) {
            $this->renderTextLines($core, $bodyLines, $fontMedium, $bodySize, $bodyLineHeight, '#f5f5f5', $padding, $bodyTopY);
        }

        return $image;
    }

    /**
     * Wrap text into lines that fit within $maxWidth using the given font.
     * Respects explicit \n line breaks. Returns an array of line strings.
     *
     * @return array<int, string>
     */
    private function wrapText(string $text, string $fontPath, int $fontSize, int $maxWidth): array
    {
        $lines = [];
        foreach (explode("\n", $text) as $paragraph) {
            $words = preg_split('/\s+/', trim($paragraph)) ?: [];
            if (empty($words)) {
                $lines[] = '';

                continue;
            }
            $current = '';
            foreach ($words as $word) {
                $candidate = $current === '' ? $word : $current.' '.$word;
                $box = imagettfbbox($fontSize, 0, $fontPath, $candidate);
                $width = abs($box[2] - $box[0]);
                if ($width > $maxWidth && $current !== '') {
                    $lines[] = $current;
                    $current = $word;
                } else {
                    $current = $candidate;
                }
            }
            if ($current !== '') {
                $lines[] = $current;
            }
        }

        return $lines;
    }

    /**
     * Compute total visual height of a wrapped text block. We rely on the actual
     * font ascent (from imagettfbbox of an x-height-tall sample) plus
     * (n-1) * line_spacing for a tight fit.
     *
     * @param  array<int, string>  $lines
     */
    private function measureBlockHeight(array $lines, int $fontSize, float $lineHeight): int
    {
        if (empty($lines)) {
            return 0;
        }
        $lineSpacing = (int) round($fontSize * $lineHeight);

        return $lineSpacing * count($lines);
    }

    /**
     * Render an array of lines line-by-line via imagettftext at explicit y positions.
     * $topY is where the first line's bounding box starts (top of first glyph row).
     *
     * @param  array<int, string>  $lines
     */
    private function renderTextLines($core, array $lines, string $fontPath, int $fontSize, float $lineHeight, string $hexColor, int $x, int $topY): void
    {
        $color = $this->allocateColor($core, $hexColor);
        $lineSpacing = (int) round($fontSize * $lineHeight);
        // imagettftext positions the text at the BASELINE. The font's ascent for our
        // body line height is roughly fontSize * 0.78 — we use that as the offset
        // from $topY to the first baseline.
        $ascent = (int) round($fontSize * 0.82);
        $baselineY = $topY + $ascent;

        foreach ($lines as $line) {
            imagettftext($core, $fontSize, 0, $x, $baselineY, $color, $fontPath, $line);
            $baselineY += $lineSpacing;
        }
    }

    /**
     * Allocate a GD color from a hex string (#rrggbb).
     *
     * @return int Color identifier suitable for GD draw functions.
     */
    private function allocateColor($core, string $hex): int
    {
        [$r, $g, $b] = $this->hexToRgb($hex);

        $color = imagecolorallocate($core, $r, $g, $b);

        return $color === false ? imagecolorallocate($core, 255, 255, 255) : $color;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Paint a smooth black-to-transparent gradient over the bottom of the image
     * directly on the GD resource. Avoids visible bands from stacked rectangles.
     *
     * @param  float  $easingPower  1.0 = linear, 2.0 = quadratic (slow start),
     *                              <1.0 = ramps up faster at the top.
     */
    private function applyBottomGradient(ImageInterface $image, float $heightFraction, float $maxAlpha, float $easingPower = 2.0): void
    {
        $maskHeight = (int) ($this->height * $heightFraction);
        $maskStart = $this->height - $maskHeight;

        $core = $image->core()->native(); // GD resource
        imagealphablending($core, true);

        for ($y = 0; $y < $maskHeight; $y++) {
            $progress = $y / max(1, $maskHeight - 1);
            $alphaFraction = (float) (pow($progress, $easingPower) * $maxAlpha);
            // GD alpha is inverted: 0=opaque, 127=transparent.
            $gdAlpha = (int) round(127 * (1 - $alphaFraction));
            $color = imagecolorallocatealpha($core, 0, 0, 0, $gdAlpha);
            imagefilledrectangle($core, 0, $maskStart + $y, $this->width - 1, $maskStart + $y, $color);
            imagecolordeallocate($core, $color);
        }
    }

    private function renderFooter(ImageInterface $canvas, SocialAccount $socialAccount): ImageInterface
    {
        // Footer uses Inter Light (300) in slate-grey — always legible on top
        // of the bottom dark gradient applied by Template A.
        $footerColor = '#9ca3af';

        $username = $socialAccount->username ?? '';
        $displayName = $socialAccount->display_name ?? '';

        // Footer row anchored from the bottom: avatar + handle + displayName
        // share the same vertical center so they line up cleanly.
        $avatarSize = 48;
        $avatarX = 60;
        $rowCenterY = $this->height - 100; // 100px from the bottom edge

        $avatarY = $rowCenterY - (int) ($avatarSize / 2);

        $textX = $avatarX + $avatarSize + 16;
        // intervention/image's `align('left', 'top')` positions text at its EM-box
        // top. Inter's visual glyph midpoint sits roughly at top + size * 0.42, so
        // we shift textY up by that amount to land its center on rowCenterY.
        $textY = $rowCenterY - (int) round(24 * 0.42);

        // Avatar (circular)
        $avatarBinary = $this->fetchAvatarBinary($socialAccount);
        if ($avatarBinary !== null) {
            $this->drawCircularAvatar($canvas, $avatarBinary, $avatarX, $avatarY, $avatarSize);
        }

        $fontLight = $this->fontPath('Inter-Light.ttf');
        if (! $fontLight || ! file_exists($fontLight)) {
            return $canvas;
        }

        if ($username) {
            $canvas->text('@'.$username, $textX, $textY, function (FontFactory $font) use ($fontLight, $footerColor) {
                $font->filename($fontLight);
                $font->size(24);
                $font->color($footerColor);
                $font->align('left', 'top');
            });
        }

        if ($displayName) {
            $canvas->text($displayName, $this->width - 60, $textY, function (FontFactory $font) use ($fontLight, $footerColor) {
                $font->filename($fontLight);
                $font->size(24);
                $font->color($footerColor);
                $font->align('right', 'top');
            });
        }

        return $canvas;
    }

    /**
     * Fetches the avatar binary via Storage (works with local, R2, S3 — whatever
     * `filesystems.default` is). Returns null when there's no avatar or the read fails.
     */
    private function fetchAvatarBinary(SocialAccount $socialAccount): ?string
    {
        $rawPath = $socialAccount->getRawOriginal('avatar_url');
        if (! $rawPath) {
            return null;
        }

        try {
            if (! Storage::exists($rawPath)) {
                return null;
            }
            $contents = Storage::get($rawPath);

            return $contents ?: null;
        } catch (\Throwable $e) {
            Log::warning('TemplateImageGenerator: avatar fetch failed', [
                'account' => $socialAccount->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Draws a circular avatar by overlaying a per-pixel alpha-masked GD truecolor
     * onto the canvas. Pixels outside the inscribed circle become fully transparent.
     */
    private function drawCircularAvatar(ImageInterface $canvas, string $avatarBinary, int $x, int $y, int $size): void
    {
        $core = $canvas->core()->native(); // GD resource

        $src = @imagecreatefromstring($avatarBinary);
        if (! $src) {
            return;
        }

        // Square-crop center, then resize to $size x $size.
        $sw = imagesx($src);
        $sh = imagesy($src);
        $crop = min($sw, $sh);
        $cx = (int) (($sw - $crop) / 2);
        $cy = (int) (($sh - $crop) / 2);
        $resized = imagecreatetruecolor($size, $size);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefill($resized, 0, 0, $transparent);
        imagecopyresampled($resized, $src, 0, 0, $cx, $cy, $size, $size, $crop, $crop);
        imagedestroy($src);

        // Apply circular alpha mask.
        $cx = $size / 2;
        $cy = $size / 2;
        $r = $size / 2;
        for ($py = 0; $py < $size; $py++) {
            for ($px = 0; $px < $size; $px++) {
                $dx = $px + 0.5 - $cx;
                $dy = $py + 0.5 - $cy;
                $dist = sqrt($dx * $dx + $dy * $dy);
                if ($dist > $r) {
                    imagesetpixel($resized, $px, $py, $transparent);
                }
            }
        }

        // Composite onto the main canvas (alpha-aware).
        imagealphablending($core, true);
        imagecopy($core, $resized, $x, $y, 0, 0, $size, $size);
        imagedestroy($resized);
    }

    /**
     * Render text at a precise (x, baselineY) position with optional letter spacing.
     * Letter spacing > 0 renders each character individually with extra pixels between glyphs.
     */
    private function drawTextAt($core, string $text, string $fontPath, int $fontSize, string $hexColor, int $x, int $baselineY, int $letterSpacing = 0): void
    {
        $color = $this->allocateColor($core, $hexColor);

        if ($letterSpacing <= 0) {
            imagettftext($core, $fontSize, 0, $x, $baselineY, $color, $fontPath, $text);

            return;
        }

        $cursor = $x;
        $chars = mb_str_split($text);
        foreach ($chars as $char) {
            imagettftext($core, $fontSize, 0, $cursor, $baselineY, $color, $fontPath, $char);
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $char);
            $cursor += abs($bbox[2] - $bbox[0]) + $letterSpacing;
        }
    }

    /**
     * Measure the on-screen width of a string when rendered with extra letter spacing.
     */
    private function measureLetterSpacedWidth(string $text, string $fontPath, int $fontSize, int $letterSpacing): int
    {
        if ($letterSpacing <= 0) {
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);

            return abs($bbox[2] - $bbox[0]);
        }

        $width = 0;
        $chars = mb_str_split($text);
        $count = count($chars);
        foreach ($chars as $i => $char) {
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $char);
            $width += abs($bbox[2] - $bbox[0]);
            if ($i < $count - 1) {
                $width += $letterSpacing;
            }
        }

        return $width;
    }

    /**
     * Draw a 1px horizontal line on the GD resource with optional alpha (0..1).
     */
    private function drawHorizontalLine($core, int $x1, int $x2, int $y, string $hexColor, float $alpha = 1.0): void
    {
        [$r, $g, $b] = $this->hexToRgb($hexColor);
        // GD alpha: 0 opaque, 127 transparent.
        $gdAlpha = (int) round(127 * (1 - max(0.0, min(1.0, $alpha))));
        imagealphablending($core, true);
        $color = imagecolorallocatealpha($core, $r, $g, $b, $gdAlpha);
        imageline($core, $x1, $y, $x2, $y, $color);
        imagecolordeallocate($core, $color);
    }

    private function fontPath(string $filename): ?string
    {
        $path = base_path('resources/fonts/'.$filename);

        return file_exists($path) ? $path : null;
    }
}
