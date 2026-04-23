# Media Format Matrix for Social Networks

**Date:** 2026-04-23
**Status:** Draft — needs verification of cells marked `?`

Reference map of accepted media formats per platform/variant. Drives the `MediaConverter` service design.

## Images

| Platform / Variant | JPEG | PNG | WebP | GIF | HEIC | Max size | Max dimensions | Aspect ratio | Notes |
|-------------------|------|-----|------|-----|------|----------|----------------|--------------|-------|
| **Instagram Feed** | ✅ | ❌ | ❌ | ❌ | ❌ | ~8 MB | 1920 × 1080 | 4:5 to 1.91:1 | JPEG **only** per official docs. PNG returns error 2207052/2207005. |
| **Instagram Reel** | ❌ | ❌ | ❌ | ❌ | ❌ | — | — | — | **Reel = video only** (media_type REELS requires video_url) |
| **Instagram Story** | ✅ | ❌ | ❌ | ❌ | ❌ | ~8 MB | 1920 × 1080 | 9:16 preferred | JPEG only. Same rules as Feed for image stories. |
| **Facebook Post** | ✅ | ✅ | ? | ? | ❌ | 4 MB | ? | wide range | Postiz maps error 1366046 for size/format violations |
| **Facebook Reel** | ❌ | ❌ | ❌ | ❌ | ❌ | — | — | — | Video only |
| **Facebook Story** | ✅ | ✅ | ? | ? | ❌ | 4 MB | ? | 9:16 preferred | Same as Post format-wise |
| **LinkedIn Post** | ✅ | ✅ | ? | ❌ | ❌ | 5 MB | 6000 × 6000 | wide range | JPEG/PNG safest |
| **LinkedIn Carousel** | ✅ | ✅ | ? | ❌ | ❌ | 5 MB each | 6000 × 6000 | 1:1 to 1:1.91 | Up to 20 images |
| **TikTok Video** | ❌ | ❌ | ❌ | ❌ | ❌ | — | — | — | Video only via this content_type |
| **TikTok Photo** (`auto_add_music`) | ✅ | ✅ | ? | ❌ | ❌ | ? | ? | ? | Postiz has `convertToJPEG: true` |
| **YouTube Shorts** | ❌ | ❌ | ❌ | ❌ | ❌ | — | — | — | Video only |
| **X Post** | ✅ | ✅ | ✅ | ✅ | ❌ | 5 MB | 8192 × 8192 | ≥ 4:1 to ≥ 1:3 | Up to 4 images or 1 video |
| **Threads Post** | ✅ | ✅ | ✅ | ❌ | ❌ | 8 MB | ? | 10:16.5 max | Same as Instagram stack |
| **Pinterest Pin** | ✅ | ✅ | ? | ❌ | ❌ | 20 MB | ? | 2:3 preferred | |
| **Pinterest Carousel** | ✅ | ✅ | ? | ❌ | ❌ | 20 MB | ? | 2:3 preferred | 2–5 images |
| **Bluesky Post** | ✅ | ✅ | ✅ | ✅ | ❌ | **976 KB** | ? | flexible | Postiz auto-resizes to fit 976 KB |
| **Mastodon Post** | ✅ | ✅ | ✅ | ✅ | ❌ | 10 MB (instance-dep) | ? | flexible | Varies by instance |

## Videos

| Platform / Variant | MP4 | MOV | WebM | Max size | Max duration | Min duration | Aspect ratio | Notes |
|-------------------|-----|-----|------|----------|--------------|--------------|--------------|-------|
| **Instagram Feed (video)** | ✅ | ✅ | ❌ | 100 MB | 60 min | 3s | 4:5 to 1.91:1 | Becomes VIDEO or REELS based on aspect |
| **Instagram Reel** | ✅ | ✅ | ❌ | 1 GB | 15 min | 3s | 9:16 preferred | H.264, AAC audio |
| **Instagram Story** | ✅ | ✅ | ❌ | 100 MB | 60s | 3s | 9:16 | |
| **Facebook Post (video)** | ✅ | ✅ | ? | 10 GB | 240 min | — | flexible | Very permissive |
| **Facebook Reel** | ✅ | ✅ | ? | 1 GB | 90s | 3s | 9:16 | |
| **Facebook Story** | ✅ | ✅ | ? | ? | 60s | — | 9:16 | |
| **LinkedIn Post** | ✅ | ? | ? | 5 GB | 10 min | 3s | flexible | |
| **TikTok Video** | ✅ | ✅ | ✅ | max from `creator_info` | max from `creator_info` | 3s | 9:16 preferred | Duration fetched from API per account |
| **YouTube Shorts** | ✅ | ✅ | ✅ | 256 GB | 60s | — | 9:16 | Shorts detection is automatic on vertical vídeo ≤ 60s |
| **X Post (video)** | ✅ | ✅ | ❌ | 512 MB | 140s (std) / 2h 20m (Premium) | 0.5s | wide range | Postiz caps at 2min |
| **Threads Post (video)** | ✅ | ✅ | ❌ | 1 GB | 5 min | — | flexible | |
| **Pinterest Video Pin** | ✅ | ✅ | ❌ | 2 GB | 15 min | 4s | flexible | |
| **Bluesky Post (video)** | ✅ | ✅ | ❌ | 100 MB | 60s | — | flexible | Upload via dedicated endpoint |
| **Mastodon Post (video)** | ✅ | ? | ✅ | 40 MB (instance-dep) | instance-dep | — | flexible | |

## Per-platform "from-to" image normalization

**Philosophy:** every media item passes through the converter before publishing. No env flag. Converter is a no-op when source already matches the platform's accepted set.

| Platform / Variant | Accepted natively (no-op) | Everything else converts to | Also enforces |
|-------------------|---------------------------|-----------------------------|---------------|
| Instagram Feed / Story | JPEG | **JPEG** (q90) | ≤ 1920×1080, aspect 4:5..1.91:1 |
| Instagram Reel | — (video only) | — | — |
| Facebook Post / Story | JPEG | **JPEG** (q90) | ≤ 4 MB |
| Facebook Reel | — (video only) | — | — |
| TikTok Photo mode | JPEG | **JPEG** (q90) | — |
| LinkedIn Post / Carousel | JPEG, PNG | **JPEG** (q90) from WebP/HEIC/GIF | ≤ 5 MB, ≤ 6000×6000 |
| X Post | JPEG, PNG, WebP, GIF | **JPEG** (q90) from HEIC | ≤ 5 MB |
| Threads Post | JPEG, PNG, WebP | **JPEG** (q90) from HEIC/GIF | ≤ 8 MB |
| Pinterest Pin / Carousel | JPEG, PNG | **JPEG** (q90) from WebP/HEIC/GIF | ≤ 20 MB |
| Bluesky Post | JPEG, PNG, WebP, GIF | **JPEG** from HEIC; **iterative resize** if > 976 KB | ≤ 976 KB |
| Mastodon Post | JPEG, PNG, WebP, GIF | **JPEG** (q90) from HEIC | instance-dep |

**Derived rules:**

- **HEIC → always JPEG** (nenhuma plataforma aceita HEIC)
- **WebP → JPEG** para Meta (IG/FB), TikTok Photo, LinkedIn, Pinterest; nativo em X/Threads/Bluesky/Mastodon
- **PNG → JPEG** para Meta + TikTok Photo; nativo no resto
- **GIF → JPEG** (primeiro frame) para Meta + LinkedIn + Pinterest + Threads; nativo em X/Bluesky/Mastodon
- **Animated GIF / Animated WebP**: perde animação quando convertido (limitação v1, flag futuro)

## Video normalization (v1)

**Fora do escopo do converter.** Vídeos passam direto pelo publisher — plataformas validam server-side e a gente surfa erro pro usuário. Transcoding (FFmpeg, codec fix, re-mux) fica pra v2.

## Converter service design

### Service signature

```php
namespace App\Services\Media;

use App\Enums\PostPlatform\ContentType;
use App\Models\Media;

class MediaConverter
{
    /**
     * Ensure a media item is compatible with the given content_type.
     * Returns a Media (original or a newly-created converted copy).
     * Idempotent: if the original already complies, returns it as-is.
     * Cached: repeated calls for the same (media, content_type) reuse the previously-converted file.
     */
    public function ensureCompatible(Media $media, ContentType $contentType): Media;
}
```

### Rules catalog

A single class centralizes the "from-to" table above:

```php
final class PlatformMediaRules
{
    public function __construct(
        public readonly array $acceptedImageMimes,   // ['image/jpeg']
        public readonly string $targetImageMime,     // 'image/jpeg'
        public readonly int $targetJpegQuality,      // 90
        public readonly ?int $maxImageWidth,         // 1920
        public readonly ?int $maxImageHeight,        // 1080
        public readonly ?int $maxImageBytes,         // 4_000_000
    ) {}

    public static function for(ContentType $ct): self { /* match per variant */ }
}
```

### When it's called

Each publisher invokes once, at the top of `publish()`:

```php
public function publish(PostPlatform $pp): array
{
    $media = $pp->post->mediaItems->map(
        fn ($m) => $this->converter->ensureCompatible($m, $pp->content_type),
    );
    // continue with normalized $media
}
```

Same pattern in every publisher: Instagram, Facebook, TikTok, LinkedIn, X, Threads, Pinterest, Bluesky, Mastodon, YouTube.

### Pipeline (image)

```
1. Fetch original file from storage
2. Detect real MIME via finfo (not trusting extension)
3. If MIME ∈ acceptedImageMimes AND bytes ≤ maxImageBytes AND dims ≤ max → return original Media (no-op)
4. Else:
   a. Load into Intervention\Image
   b. Resize if > maxImageWidth/Height (keep aspect)
   c. For Bluesky: loop shrinking 10% until bytes ≤ 976 KB (Postiz precedent)
   d. Encode to targetImageMime at targetJpegQuality
   e. Upload to storage: `converted/{media_id}/{file_hash}/{content_type_value}.jpg`
   f. Create Media row pointing at converted file (or return cached row if exists)
   g. Return the new Media
```

### Caching / idempotency

Path strategy: `converted/{original_media_id}/{original_file_sha256_prefix}/{content_type_value}.{ext}`

- If original file changes → sha256 differs → new path → fresh conversion
- If same original + same target → path exists → reuse
- DB table `media_conversions` (migration) tracks `(source_media_id, content_type, derived_media_id)` for fast lookup without listing storage

### Failure handling

- Conversion happens inside the publish job (already async/queued).
- On failure, publisher catches and marks `post_platform.status = failed` with a clear error ("Could not convert image for Instagram: PNG to JPEG conversion failed"). Same path as any other publish failure.
- Original media is never mutated or deleted. Converted derivatives are never mutated either.

## Dependencies

- **`intervention/image` v3.x** (composer install)
- PHP GD or Imagick extension (already required by Laravel's `Illuminate/Http/UploadedFile` image validation — safe assumption)
- No new queue, no config changes
- **New migration:** `media_conversions` table

## Decisions (confirmed with user)

1. **Bluesky iterative resize:** replicate Postiz — shrink 10% per iteration until ≤ 976 KB. Done **in memory at publish time**, not stored on CDN (Bluesky API accepts bytes directly).
2. **Converted file storage:** **replace the original in place** at upload time. No parallel `converted/` directory, no `media_conversions` table, no cleanup job. One file per Media, always JPEG.
3. **GIF policy:** accepted only on X, Bluesky, Mastodon. Frontend validation blocks submit when GIF is attached and a GIF-incompatible platform is selected. No silent first-frame conversion.

## Revised pipeline

**Principle:** upload-time touches **only the format** (pixel-perfect). Dimensions and bytes are preserved for maximum platform flexibility (9:16 Reel, 4:5 carousel, etc.). Per-platform size/dimension constraints are handled at publish time in memory, without mutating the CDN original.

### At upload time (asset controller)

```
1. Receive upload
2. Detect real MIME via finfo
3. If MIME is an image AND format ≠ JPEG AND format ≠ GIF:
   a. Load into Intervention\Image (keeps original dimensions + aspect)
   b. Encode as JPEG q100 (no quality loss — format conversion only) with SAME dimensions
   c. Overwrite the original file at `medias/{uuid}.jpg`
   d. Save Media row with mime_type='image/jpeg'
4. GIFs: keep as GIF (for X/Bluesky/Mastodon only). Frontend blocks incompatible platforms.
5. JPEG already: no-op, store as-is.
6. Videos: unchanged v1 (passthrough).
```

### At publish time (minimal — no resize except Bluesky)

All publishers pass the **single CDN URL** stored on the Media row. No temporary variants, no resize-for-Graph-API dance, no cleanup jobs.

| Platform | Handling at publish |
|----------|---------------------|
| Instagram, Facebook, LinkedIn, X, Threads, Pinterest, TikTok Photo, YouTube thumb, Mastodon | Pass `media.url` as-is to the platform API. If the platform rejects for dimensions/size, the error surfaces to the user via `post_platform.error_message`. |
| **Bluesky (only exception)** | Bluesky's API accepts raw bytes (not URL). Before upload, check byte size: if > 976 KB, do Postiz-style iterative 10 %-shrink loop **in memory** — shrinks only dimensions (not quality — keeps q100). Loops until ≤ 976 KB. Then upload bytes. No CDN round-trip, no temp files. |

### Filename preservation

- UUID stays the same (`abc-123...`)
- Only the extension changes when the real format changes (`.png` → `.jpg`, `.webp` → `.jpg`)
- The DB `media.path` updates (`medias/abc-123.png` → `medias/abc-123.jpg`)
- Old file is deleted from CDN after the new one is written
- Frontend's `Media` object is returned with the updated URL so the editor displays the right image

Why the extension must change: some platform APIs check extension in addition to Content-Type. Keeping `.png` on a file with JPEG bytes risks inconsistent behavior.

### Frontend validation additions

Update `useMediaRules.ts` to track per-`content_type` GIF acceptance:

| Content type | Accepts GIF? |
|--------------|--------------|
| `x_post` | ✅ |
| `bluesky_post` | ✅ |
| `mastodon_post` | ✅ |
| everything else | ❌ |

Extend `isMediaValidForContentType()` in `Edit.vue` (and per-platform compliance computeds) to reject when media contains a GIF and the current `content_type` doesn't accept it.

## Updated dependencies

- **`intervention/image` v3.x** (composer install)
- No new migrations
- No new tables
- No cleanup job

## Cells still needing verification (`?` in tables)

- Facebook Post/Story image max dimensions + WebP support
- LinkedIn Post/Carousel WebP support
- TikTok Photo mode max size/dimensions
- Pinterest WebP support + max dimensions
- Threads image max dimensions
- Mastodon instance-dependent limits

These gaps don't block — normalizing to JPEG q90 ≤ 1920×1080 ≤ ~4 MB covers all of them comfortably.

## Cells still needing verification (`?` in tables)

- Facebook Post/Story image max dimensions + WebP support
- LinkedIn Post/Carousel WebP support
- TikTok Photo mode max size/dimensions
- Pinterest WebP support + max dimensions
- Threads image max dimensions
- Mastodon instance-dependent limits

These gaps don't block the design — falling back to "convert to JPEG" covers all of them safely.
