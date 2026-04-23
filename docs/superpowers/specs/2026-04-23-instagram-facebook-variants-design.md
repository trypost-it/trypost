# Instagram and Facebook Content Variants in Post Editor

**Date:** 2026-04-23
**Branch:** feature/workspace-billing-plans

## Problem

The post editor has per-platform settings for TikTok (`TikTokSettings.vue`) but no equivalent UI for choosing content variants on Instagram (Feed / Reel / Story) and Facebook (Post / Reel / Story). The backend already supports these variants — `ContentType` enum maps to publisher branches in `InstagramPublisher` and `FacebookPublisher`, `UpdatePost` persists `content_type`, and `UpdatePostRequest` validates it — but `SyncPostPlatforms` always defaults to `InstagramFeed` / `FacebookPost` and the frontend has no way for users to change it.

Users currently can only publish Feed posts on Instagram and standard Posts on Facebook, even when they want Reels or Stories.

## Goals

- Let users choose content variant per connected Instagram and Facebook account on the post edit page.
- Match the visual pattern established by `TikTokSettings.vue` (collapsible card with platform context).
- Support multiple connected accounts of the same platform (each card is independent — user can pick different variants for different accounts in the same post).
- Block submission with a clear warning when the chosen variant is incompatible with the attached media (mirrors existing `tiktokComplianceValid` pattern).
- Centralize media rules in a single source of truth, re-used for validation and for per-card warnings.

## Non-Goals

- Backend changes (publishers already handle all variants).
- Variant support for platforms beyond Instagram and Facebook (LinkedIn, Pinterest, etc. come later).
- Restricting the media upload picker based on variant — the variant-driven accept/max logic stays on the validation side, not on the file input (user can still drop any media; we just warn + block publish).

## Design

### New Files

- `resources/js/components/posts/editor/InstagramSettings.vue` — rendered for `Platform::Instagram` and `Platform::InstagramFacebook` (same variant set).
- `resources/js/components/posts/editor/FacebookSettings.vue` — rendered for `Platform::Facebook`.
- `resources/js/composables/useMediaRules.ts` — restored from `main`, single source of truth for per-`content_type` media constraints.

### Modified Files

- `resources/js/pages/posts/Edit.vue` — add `platformContentTypes` state, compliance computeds, update `getSubmitData()` and autosave watcher, pipe new state into `ScheduleTab` and `PreviewTab`.
- `resources/js/components/posts/editor/ScheduleTab.vue` — accept `platformContentTypes` prop, emit `update:platform-content-type`, render `InstagramSettings` / `FacebookSettings` blocks per selected account.
- `resources/js/components/posts/editor/PreviewTab.vue` — read variant from `platformContentTypes[previewPlatform.id]` so preview updates live when user toggles variants.
- `lang/en/posts.php`, `lang/es/posts.php`, `lang/pt-BR/posts.php` — add translation keys for settings card, variant labels, warnings.

### Component Structure (`InstagramSettings.vue`, `FacebookSettings.vue`)

Mirror `TikTokSettings.vue`'s outer shell:

- Collapsible root card with border.
- Header button: platform icon + `"Instagram settings"` / `"Facebook settings"` + chevron up/down.
- Closed by default; clicking the header toggles `open` ref.
- Body (when open):
  - "Posting to" row: `Avatar` component + display name + `@username` of the connected account (critical when the user has multiple accounts of the same platform — makes clear which card configures which account).
  - Variant picker: three horizontal pill buttons, one per variant. Active pill gets `bg-primary/10 border-primary`; inactive gets muted styling. Clicking emits `update:content-type` with the new value.
  - Warning block (conditionally rendered): when `useMediaRules(contentType)` indicates the current `media` array violates the rules, render an `IconAlertTriangle` + short i18n message explaining the specific violation ("Reel requires a video", "Story accepts only 1 media", etc.).

Props:

```ts
interface Props {
    socialAccount: SocialAccount | null;
    contentType: string;              // e.g. 'instagram_reel'
    media: MediaItem[];               // to evaluate compliance warnings
    disabled?: boolean;
}
```

Emits:

```ts
'update:content-type': [value: string]
```

### Variant Catalog

| Platform | Variants | Default |
|----------|----------|---------|
| Instagram (standalone + via Facebook) | `instagram_feed`, `instagram_reel`, `instagram_story` | `instagram_feed` |
| Facebook | `facebook_post`, `facebook_reel`, `facebook_story` | `facebook_post` |

Labels and descriptions are already defined in `App\Enums\PostPlatform\ContentType` (`label()` and `description()` methods). Frontend uses i18n keys for display so strings stay in translation files, not in enum PHP strings.

### State Management in `Edit.vue`

Add a `platformContentTypes` ref that mirrors the existing `platformMeta` pattern exactly:

```ts
const platformContentTypes = ref<Record<string, string>>(
    Object.fromEntries(
        post.value.post_platforms.map((pp) => [pp.id, pp.content_type ?? '']),
    ),
);

const updatePlatformContentType = (platformId: string, contentType: string) => {
    platformContentTypes.value = {
        ...platformContentTypes.value,
        [platformId]: contentType,
    };
};
```

This produces a dictionary keyed by `post_platform.id`, so two Instagram accounts get two independent entries.

Update `getSubmitData()` to read `content_type` from state instead of the prop:

```ts
const platforms = post.value.post_platforms
    .filter((pp) => selectedPlatformIds.value.includes(pp.id))
    .map((pp) => ({
        id: pp.id,
        content_type: platformContentTypes.value[pp.id] ?? pp.content_type,
        meta: platformMeta.value[pp.id] ?? pp.meta ?? {},
    }));
```

Add `platformContentTypes` to the autosave `watch`:

```ts
watch(
    [content, media, selectedPlatformIds, scheduledDateTime, selectedLabelIds, platformMeta, platformContentTypes],
    triggerAutosave,
    { deep: true },
);
```

### Media Rules Composable

Restore `resources/js/composables/useMediaRules.ts` from `main` (the file already covered Instagram, Facebook, and every other platform correctly). The restored file keeps its original `useMediaRules(contentTypeRef)` reactive form (used inside settings components) and adds a small non-reactive helper for use in `Edit.vue`'s validation computeds:

- `useMediaRules(contentTypeRef: Ref<string>)` — reactive, already present on `main`, for use in `<script setup>` of settings components.
- `getMediaRules(contentType: string): MediaRules` — new plain helper that returns the same rules dictionary entry imperatively, so compliance computeds in `Edit.vue` don't need to wrap in refs.

The rules object keys off `content_type` values (e.g. `instagram_reel`) and returns `{ maxFiles, minFiles?, acceptImages, acceptVideos, requiresMedia }`.

### Compliance Validation

Add a single media-vs-rules helper in `Edit.vue`:

```ts
const isMediaValidForContentType = (contentType: string, mediaItems: MediaItem[]): boolean => {
    const rules = getMediaRules(contentType);
    const videos = mediaItems.filter((m) => m.type === 'video' || m.mime_type?.startsWith('video/'));
    const images = mediaItems.filter((m) => m.type === 'image' || m.mime_type?.startsWith('image/'));
    const total = mediaItems.length;

    if (rules.requiresMedia && total === 0) return false;
    if (total > rules.maxFiles) return false;
    if (rules.minFiles && total < rules.minFiles) return false;
    if (!rules.acceptVideos && videos.length > 0) return false;
    if (!rules.acceptImages && images.length > 0) return false;
    return true;
};
```

Two new computeds parallel to the existing `tiktokComplianceValid`:

```ts
const instagramComplianceValid = computed(() =>
    post.value.post_platforms
        .filter((pp) => ['instagram', 'instagram-facebook'].includes(pp.platform)
            && selectedPlatformIds.value.includes(pp.id))
        .every((pp) => {
            const ct = platformContentTypes.value[pp.id];
            return Boolean(ct) && isMediaValidForContentType(ct, media.value);
        }),
);

const facebookComplianceValid = computed(() =>
    post.value.post_platforms
        .filter((pp) => pp.platform === 'facebook'
            && selectedPlatformIds.value.includes(pp.id))
        .every((pp) => {
            const ct = platformContentTypes.value[pp.id];
            return Boolean(ct) && isMediaValidForContentType(ct, media.value);
        }),
);
```

Submit buttons (`PickTimePopover` trigger and the primary "Post now" / "Schedule" button) gain these computeds in their `:disabled` expression, and `:title` falls back to the first failing platform's i18n message when compliance is false.

### `ScheduleTab.vue` Integration

Add prop and emit:

```ts
defineProps<{
    // ...existing props
    platformContentTypes: Record<string, string>;
}>();

defineEmits<{
    // ...existing emits
    'update:platform-content-type': [platformId: string, contentType: string];
}>();
```

Compute filtered lists (complement to existing `selectedTikTokPlatforms`):

```ts
const selectedInstagramPlatforms = computed(() =>
    props.postPlatforms.filter(
        (pp) => ['instagram', 'instagram-facebook'].includes(pp.platform)
            && props.selectedPlatformIds.includes(pp.id),
    ),
);

const selectedFacebookPlatforms = computed(() =>
    props.postPlatforms.filter(
        (pp) => pp.platform === 'facebook' && props.selectedPlatformIds.includes(pp.id),
    ),
);
```

Render blocks in the existing "Platform-specific settings" region, after the TikTok block:

```vue
<div v-if="selectedInstagramPlatforms.length > 0" class="space-y-4">
    <InstagramSettings
        v-for="pp in selectedInstagramPlatforms"
        :key="pp.id"
        :social-account="pp.social_account"
        :content-type="platformContentTypes[pp.id] ?? ''"
        :media="media ?? []"
        :disabled="isReadOnly"
        @update:content-type="emit('update:platform-content-type', pp.id, $event)"
    />
</div>

<div v-if="selectedFacebookPlatforms.length > 0" class="space-y-4">
    <FacebookSettings
        v-for="pp in selectedFacebookPlatforms"
        :key="pp.id"
        :social-account="pp.social_account"
        :content-type="platformContentTypes[pp.id] ?? ''"
        :media="media ?? []"
        :disabled="isReadOnly"
        @update:content-type="emit('update:platform-content-type', pp.id, $event)"
    />
</div>
```

### Preview Reactivity

`PreviewTab.vue` currently receives `contentType` as a prop read from `previewPlatform.content_type` (i.e., the server-side value from the last save). Update `Edit.vue`'s template to read from local state so the preview updates live:

```vue
<PreviewTab
    v-if="previewPlatform"
    :platform="previewPlatform.platform"
    :content="content"
    :media="media"
    :social-account="previewPlatform.social_account"
    :content-type="platformContentTypes[previewPlatform.id] ?? previewPlatform.content_type"
/>
```

### Translation Keys

Add under `posts.form.instagram.*` and `posts.form.facebook.*` (for all three locales):

- `settings` — "Instagram settings" / "Facebook settings"
- `posting_to` — "Posting to"
- `variant.feed`, `variant.reel`, `variant.story` (IG) — pill labels
- `variant.post`, `variant.reel`, `variant.story` (FB) — pill labels
- `variant.*_description` — sub-text for pills (optional, for a11y title)
- `warning.requires_media`, `warning.max_files_exceeded`, `warning.min_files_required`, `warning.no_video_allowed`, `warning.no_image_allowed` — specific violation messages
- `compliance_incomplete` — button tooltip when submit is blocked

## Data Flow

1. Backend seeds `post_platforms.content_type` with `ContentType::defaultFor($platform)` in `SyncPostPlatforms`.
2. `Edit.vue` reads the incoming `post.post_platforms[].content_type` and initializes `platformContentTypes`.
3. User opens Instagram / Facebook card, picks a variant pill → `InstagramSettings`/`FacebookSettings` emits `update:content-type` → `ScheduleTab` re-emits with `pp.id` → `Edit.vue` updates `platformContentTypes` state.
4. Autosave watcher fires → `getSubmitData()` builds platforms array with updated `content_type` → PUT to `app.posts.update` → `UpdatePost::execute` writes `content_type` on `post_platforms`.
5. Preview, warnings, and submit-button gating all read from the live `platformContentTypes` so changes are immediate.
6. At publish time, `InstagramPublisher::publish()` / `FacebookPublisher::publish()` branch on `content_type` (already implemented) and call the correct Graph API endpoint.

## Error Handling

- All card interactions respect `isReadOnly` (published / partially_published posts), consistent with existing patterns.
- If `platformContentTypes[pp.id]` is somehow empty (e.g. legacy row), the card falls back to the prop value, and the compliance computed returns `false`, blocking submit until the user explicitly picks a variant.
- Invalid `content_type` values sent to the backend are rejected by `UpdatePostRequest`'s `Rule::in(ContentType::cases())` validation.

## Testing

- Update or add a feature test for `PostController::update` that submits a payload with `platforms[].content_type = 'instagram_reel'` and asserts the `post_platform` row is updated.
- Add a unit/feature test that `instagram-facebook` platform rows accept Instagram content types (same set as standalone Instagram).
- (Optional) Pest Browser test: open an edit page with an Instagram account connected, click the card, select "Reel", confirm the preview updates and autosave fires.

## Rollout

Single feature branch, single PR. No migration, no config flags. Backend is already capable — this is purely a frontend/UX enablement. Deploying the change instantly unlocks Reel/Story publishing for all users who have an Instagram or Facebook account connected.
