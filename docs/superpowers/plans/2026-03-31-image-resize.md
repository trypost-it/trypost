# Image Resize Per Platform Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Automatically optimize images before publishing to each social platform — resize, convert format, and reduce quality to meet each platform's limits.

**Architecture:** A `MediaOptimizer` service with per-platform config (max width, max size, format, quality). Each publisher calls `optimizeImage()` before uploading. Uses a quality reduction loop to guarantee file size compliance.

**Tech Stack:** Laravel 13, PHP 8.4, Intervention Image v4, Pest 4

**Spec:** `docs/superpowers/specs/2026-03-31-image-resize-design.md`

---

### Task 1: Install Intervention Image

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Install the package**

```bash
composer require intervention/image
```

- [ ] **Step 2: Verify installation**

```bash
php artisan tinker --execute "echo Intervention\Image\ImageManager::class;"
```

Expected: `Intervention\Image\ImageManager`

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: install intervention/image v4"
```

---

### Task 2: Create MediaOptimizer service with tests

**Files:**
- Create: `app/Services/Media/MediaOptimizer.php`
- Test: `tests/Unit/Services/Media/MediaOptimizerTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Unit/Services/Media/MediaOptimizerTest.php` with tests:

1. `it optimizes image for instagram (converts to jpeg, max 1440px width)`
   - Create a 2000px wide PNG test image using Intervention
   - Optimize for Instagram
   - Assert output is JPEG, width <= 1440, file size <= 8MB

2. `it optimizes image for bluesky (under 1MB)`
   - Create a large JPEG test image
   - Optimize for Bluesky
   - Assert output file size < 1MB (976KB)

3. `it reduces quality to meet size limit`
   - Create a high-quality large image
   - Optimize for Bluesky (976KB limit)
   - Assert output fits within limit

4. `it does not upscale small images`
   - Create a 500px wide image
   - Optimize for Instagram (max 1440px)
   - Assert width stays 500px (not upscaled)

5. `it returns original if already within limits`
   - Create a small JPEG under all limits
   - Optimize for Facebook
   - Assert output exists and is valid JPEG

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter=MediaOptimizer
```

- [ ] **Step 3: Implement MediaOptimizer**

Create `app/Services/Media/MediaOptimizer.php` with:
- `optimizeImage(string $filePath, Platform $platform): string` — returns path to optimized temp file
- `getImageConfig(Platform $platform): array` — returns config per platform from spec
- Quality reduction loop: if file exceeds max_size, reduce quality by 10 until it fits or quality reaches 30

Use the code from the spec. Use `ImageManager::gd()` as the driver.

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test --compact --filter=MediaOptimizer
```

- [ ] **Step 5: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Media/MediaOptimizer.php tests/Unit/Services/Media/MediaOptimizerTest.php
git commit -m "feat: add MediaOptimizer service with per-platform image optimization"
```

---

### Task 3: Integrate MediaOptimizer into BlueskyPublisher

**Files:**
- Modify: `app/Services/Social/BlueskyPublisher.php`

Bluesky is the most critical — hard 1MB limit.

- [ ] **Step 1: Update uploadBlob method**

In `BlueskyPublisher::uploadBlob()`, after downloading to temp file and before uploading:

```php
// If it's an image, optimize for Bluesky
if (str_starts_with($mimeType, 'image/')) {
    $optimizer = app(MediaOptimizer::class);
    $optimizedPath = $optimizer->optimizeImage($tempFile, Platform::Bluesky);
    @unlink($tempFile);
    $tempFile = $optimizedPath;
    $mimeType = 'image/jpeg'; // MediaOptimizer converts to JPEG
}
```

Remove the existing "Bluesky has 1MB limit" warning log — the optimizer handles it now.

- [ ] **Step 2: Run tests**

```bash
php artisan test --compact --filter=Bluesky
```

- [ ] **Step 3: Commit**

```bash
git commit -m "feat: BlueskyPublisher uses MediaOptimizer for 1MB image limit"
```

---

### Task 4: Integrate MediaOptimizer into X, LinkedIn, LinkedInPage publishers

**Files:**
- Modify: `app/Services/Social/XPublisher.php`
- Modify: `app/Services/Social/LinkedInPublisher.php`
- Modify: `app/Services/Social/LinkedInPagePublisher.php`

These publishers upload images directly (not via URL pull).

- [ ] **Step 1: Update XPublisher::uploadMedia**

In the `uploadMedia` method, after downloading to temp file and before upload, optimize images:

```php
if (str_starts_with($mimeType, 'image/') && !str_starts_with($mimeType, 'image/gif')) {
    $optimizer = app(MediaOptimizer::class);
    $optimizedPath = $optimizer->optimizeImage($tempFile, Platform::X);
    @unlink($tempFile);
    $tempFile = $optimizedPath;
    $mimeType = 'image/jpeg';
    $fileSize = filesize($tempFile);
}
```

Note: Skip GIFs — they need special handling (animated).

- [ ] **Step 2: Update LinkedInPublisher::uploadImage**

In the `uploadImage` method, optimize before uploading:

```php
$optimizer = app(MediaOptimizer::class);
$optimizedPath = $optimizer->optimizeImage($tempFile, Platform::LinkedIn);
```

- [ ] **Step 3: Update LinkedInPagePublisher::uploadImage**

Same as LinkedIn but with `Platform::LinkedInPage`.

- [ ] **Step 4: Run tests**

```bash
php artisan test --compact
```

- [ ] **Step 5: Commit**

```bash
git commit -m "feat: X, LinkedIn, LinkedInPage publishers use MediaOptimizer for images"
```

---

### Task 5: Integrate MediaOptimizer into Mastodon and Pinterest publishers

**Files:**
- Modify: `app/Services/Social/MastodonPublisher.php`
- Modify: `app/Services/Social/PinterestPublisher.php`

- [ ] **Step 1: Update MastodonPublisher::uploadMedia**

After downloading to temp file, optimize images before upload:

```php
if (str_starts_with($mimeType, 'image/') && !str_starts_with($mimeType, 'image/gif')) {
    $optimizer = app(MediaOptimizer::class);
    $optimizedPath = $optimizer->optimizeImage($tempFile, Platform::Mastodon);
    @unlink($tempFile);
    $tempFile = $optimizedPath;
}
```

Note: Mastodon `uploadMedia` currently doesn't detect mime type from the media model. Need to pass it through or detect from temp file.

- [ ] **Step 2: Update PinterestPublisher**

Pinterest image pins upload images. Optimize before the multipart upload.

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact
```

- [ ] **Step 4: Commit**

```bash
git commit -m "feat: Mastodon, Pinterest publishers use MediaOptimizer for images"
```

---

### Task 6: Skip optimization for URL-pull platforms

**Files:** None (verification only)

Instagram, Facebook, Threads, and TikTok use URL pull — their APIs download media from our CDN. These platforms handle resize on their side. No changes needed.

- [ ] **Step 1: Verify URL-pull platforms don't need optimization**

Verify that these publishers pass `$media->url` directly to the API (not uploading binary):
- `InstagramPublisher` — uses `image_url` / `video_url` params
- `FacebookPublisher` — uses `url` / `file_url` params
- `ThreadsPublisher` — uses `image_url` / `video_url` params
- `TikTokPublisher` — uses `PULL_FROM_URL` source

No code changes needed. Just verify and document.

- [ ] **Step 2: Commit verification note**

No commit needed — just verification.

---

### Task 7: Final verification

- [ ] **Step 1: Run full test suite**

```bash
php artisan test --compact
```

All tests must pass.

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Final commit and push**

```bash
git push
```
