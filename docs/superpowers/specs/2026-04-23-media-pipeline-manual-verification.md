# Manual verification checklist — media pipeline

After code changes to upload conversion + publish flow, run these checks in the running app.

## Prerequisites

- `npm run dev` running
- User logged into trypost with:
  - At least one Instagram account connected (any flow: standalone or via Facebook)
  - At least one Facebook Page connected
  - At least one Bluesky account (optional, for the resize test)

## Check 1 — PNG upload converts to JPEG at CDN

1. Create a new post (or open an existing draft) at `/posts/*/edit`
2. Upload a **PNG** file (e.g., screenshot, logo with transparency)
3. Open browser devtools → Network tab → find the POST to `assets/store`
4. In the response, verify:
   - `mime_type` = `image/jpeg`
   - `path` ends with `.jpg`
   - `url` ends with `.jpg`
5. Open the `url` in a new tab — confirms the file is served as JPEG

## Check 2 — Metadata propagates

1. Upload an image → response `meta` object has `width` and `height` (> 0)
2. Upload a video → response `meta` has `width`, `height`, `duration` (> 0)
3. If duration is missing for videos, check devtools console for errors during `readFileMetadata`

## Check 3 — Instagram publishing (the original bug)

1. With the JPEG from Check 1, select Instagram (standalone or via Facebook) as target
2. Pick "Feed Post" variant
3. Click **Post now**
4. Wait ~10–30 seconds for the async job
5. Open the Instagram account in a browser/app — the post should appear
6. Back in trypost, `post_platform.status` should be `published` (not `failed`)

## Check 4 — GIF validation (frontend)

1. Upload a `.gif` file
2. Select **Instagram** or **Facebook** — the card should show a red warning: *"This platform does not accept GIF..."*
3. Try clicking "Post now" → button is **disabled**
4. Deselect Instagram/Facebook, select **X** only → warning disappears, button enabled
5. Click "Post now" → GIF publishes to X (animated)

## Check 5 — Video duration validation

1. Upload a video > 60 seconds (e.g., 2min MP4)
2. Select Instagram → pick **Reel** variant
3. Warning shows: *"Video is 2min long, but this post type allows up to 15min"* (Reel is 15min) — no warning expected here
4. Select **Story** instead → warning: *"Video is 2min long, but this post type allows up to 60s"*
5. Submit button disabled

## Check 6 — File size validation

1. Upload a large image (> 4MB JPEG — use a high-res photo)
2. Select **Facebook Post**
3. Warning appears: *"Image exceeds the 4.0 MB limit (yours is X.X MB)"*
4. Submit disabled

## Check 7 — Aspect ratio validation

1. Upload a **square (1:1) image**
2. Select **Instagram Reel**
3. Warning appears: *"Aspect ratio 1.00 is too wide (max 0.60)"* (Reel requires ~9:16)
4. Submit disabled

## Check 8 — Bluesky iterative resize

1. Upload an image > 976 KB that's ALSO a JPEG (not to trigger PNG conversion)
2. Select Bluesky only
3. Click "Post now"
4. Check `storage/logs/laravel.log` for MediaOptimizer entries — should NOT see quality reduction warnings (only dimension reduction if the limit is still exceeded)
5. Post appears on Bluesky with acceptable quality

## Check 9 — Non-normalized video passthrough

1. Upload a `.mp4` file
2. `media.path` in DB/response still has `.mp4` extension (no conversion attempted)
3. Publish to any platform that accepts video — works via existing flow

## Troubleshooting

- If Check 1 fails (still PNG in CDN):
  - Check `vendor/bin/composer require intervention/image` is installed
  - Check `storage/logs/laravel.log` for `HasMedia: image normalization failed`
  - GD or Imagick extension must be loaded in PHP

- If Check 3 still fails on Instagram:
  - Verify the stored image IS JPEG: `curl -I <url>` should return `Content-Type: image/jpeg`
  - Check Instagram error code in `post_platform.error_message`; 2207009 = aspect ratio, 2207004 = too large

- If videos don't validate in frontend (no warnings):
  - Browser must be able to read video metadata (codec-dependent)
  - Check devtools console for `readFileMetadata` resolving empty `{}`
