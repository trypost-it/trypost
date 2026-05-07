<script setup lang="ts">
import {
    IconAlertTriangle,
    IconChevronDown,
    IconChevronUp,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import { Avatar } from '@/components/ui/avatar';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { getPlatformLogo } from '@/composables/usePlatformLogo';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}

interface CreatorInfo {
    creator_nickname: string | null;
    creator_username: string | null;
    creator_avatar_url: string | null;
    privacy_level_options: string[];
    comment_disabled: boolean;
    duet_disabled: boolean;
    stitch_disabled: boolean;
    max_video_post_duration_sec: number | null;
}

interface Props {
    socialAccount: SocialAccount | null;
    publishConfig: Record<string, any> | null;
    creatorInfo?: CreatorInfo | null;
    creatorInfoLoading?: boolean;
    videoDurationSec?: number | null;
    meta: Record<string, any>;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    creatorInfo: null,
    creatorInfoLoading: false,
    videoDurationSec: null,
    disabled: false,
});

const emit = defineEmits<{
    'update:meta': [value: Record<string, any>];
}>();

const open = ref(false);

const updateMeta = (patch: Record<string, any>) => {
    emit('update:meta', { ...props.meta, ...patch });
};

const privacyLevel = computed({
    get: () => props.meta?.privacy_level ?? '',
    set: (value: string) => updateMeta({ privacy_level: value }),
});

const autoAddMusic = computed({
    get: () => ((props.meta?.auto_add_music ?? false) ? 'yes' : 'no'),
    set: (value: string) => updateMeta({ auto_add_music: value === 'yes' }),
});

const allowComments = computed({
    get: () => props.meta?.allow_comments ?? false,
    set: (value: boolean) => updateMeta({ allow_comments: value }),
});

const allowDuet = computed({
    get: () => props.meta?.allow_duet ?? false,
    set: (value: boolean) => updateMeta({ allow_duet: value }),
});

const allowStitch = computed({
    get: () => props.meta?.allow_stitch ?? false,
    set: (value: boolean) => updateMeta({ allow_stitch: value }),
});

const isAigc = computed({
    get: () => props.meta?.is_aigc ?? false,
    set: (value: boolean) => updateMeta({ is_aigc: value }),
});

const discloseOpen = computed({
    get: () => props.meta?.disclose ?? false,
    set: (value: boolean) => {
        if (value) {
            updateMeta({ disclose: true });
            return;
        }
        // turning disclosure off clears its sub-toggles
        updateMeta({
            disclose: false,
            brand_organic_toggle: false,
            brand_content_toggle: false,
        });
    },
});

const brandOrganicToggle = computed({
    get: () => props.meta?.brand_organic_toggle ?? false,
    set: (value: boolean) => updateMeta({ brand_organic_toggle: value }),
});

const brandContentToggle = computed({
    get: () => props.meta?.brand_content_toggle ?? false,
    set: (value: boolean) => updateMeta({ brand_content_toggle: value }),
});

// Prefer the creator_info API response; fall back to the static list from the Platform enum.
const allPrivacyOptions = computed(() => {
    const fromApi = props.creatorInfo?.privacy_level_options ?? [];
    return fromApi.length > 0
        ? fromApi
        : (props.publishConfig?.privacyLevelOptions ?? []);
});

// Branded content cannot be private (TikTok compliance).
const privacyOptions = computed(() =>
    brandContentToggle.value
        ? allPrivacyOptions.value.filter((o: string) => o !== 'SELF_ONLY')
        : allPrivacyOptions.value,
);

const commentDisabled = computed(() =>
    Boolean(props.creatorInfo?.comment_disabled),
);
const duetDisabled = computed(() => Boolean(props.creatorInfo?.duet_disabled));
const stitchDisabled = computed(() =>
    Boolean(props.creatorInfo?.stitch_disabled),
);

// Max video duration check (when creator_info is available and we have duration).
const maxDurationSec = computed(
    () => props.creatorInfo?.max_video_post_duration_sec ?? null,
);
const exceedsMaxDuration = computed(() => {
    if (maxDurationSec.value === null || props.videoDurationSec === null)
        return false;
    return props.videoDurationSec > maxDurationSec.value;
});

const privacyLabelKey: Record<string, string> = {
    PUBLIC_TO_EVERYONE: 'posts.form.tiktok.privacy.public',
    MUTUAL_FOLLOW_FRIENDS: 'posts.form.tiktok.privacy.friends',
    FOLLOWER_OF_CREATOR: 'posts.form.tiktok.privacy.followers',
    SELF_ONLY: 'posts.form.tiktok.privacy.private',
};

const hasAnyBrandToggle = computed(
    () => brandOrganicToggle.value || brandContentToggle.value,
);

// Label required by TikTok: "Paid partnership" when branded content (with or without organic),
// otherwise "Promotional content".
const promotionalTitleKey = computed(() =>
    brandContentToggle.value
        ? 'posts.form.tiktok.promotional_paid_title'
        : 'posts.form.tiktok.promotional_organic_title',
);

// If user flips branded content ON while privacy is SELF_ONLY, clear it so they must re-pick.
watch(brandContentToggle, (value) => {
    if (value && privacyLevel.value === 'SELF_ONLY') {
        privacyLevel.value = '';
    }
});

// When creator_info reveals that an interaction is disabled for this account, force the meta
// flag off so we never submit a disallowed value.
watch(
    () =>
        [
            commentDisabled.value,
            duetDisabled.value,
            stitchDisabled.value,
        ] as const,
    ([comment, duet, stitch]) => {
        const patch: Record<string, any> = {};
        if (comment && allowComments.value) patch.allow_comments = false;
        if (duet && allowDuet.value) patch.allow_duet = false;
        if (stitch && allowStitch.value) patch.allow_stitch = false;
        if (Object.keys(patch).length > 0) updateMeta(patch);
    },
);
</script>

<template>
    <div class="rounded-xl border-2 border-foreground bg-card shadow-2xs">
        <button
            type="button"
            class="flex w-full cursor-pointer items-center justify-between gap-3 p-4 text-sm"
            @click="open = !open"
        >
            <span class="flex min-w-0 items-center gap-2">
                <span
                    class="inline-flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-foreground bg-card shadow-2xs"
                >
                    <img
                        :src="getPlatformLogo('tiktok')"
                        alt="TikTok"
                        class="size-full object-cover"
                    />
                </span>
                <span class="truncate font-bold text-foreground">{{
                    $t('posts.form.tiktok.settings')
                }}</span>
                <span
                    v-if="socialAccount?.username"
                    class="truncate font-medium text-foreground/60"
                    >·&nbsp;@{{ socialAccount.username }}</span
                >
            </span>
            <IconChevronUp
                v-if="open"
                class="size-4 shrink-0 text-foreground/60"
            />
            <IconChevronDown
                v-else
                class="size-4 shrink-0 text-foreground/60"
            />
        </button>

        <div
            v-if="open"
            class="space-y-5 border-t-2 border-foreground/10 px-4 pt-4 pb-4"
        >
            <p
                v-if="creatorInfoLoading"
                class="flex items-center gap-2 text-xs font-medium text-foreground/60"
            >
                <span
                    class="inline-block size-3 animate-pulse rounded-full bg-foreground/30"
                />
                {{ $t('posts.form.tiktok.creator_info_loading') }}
            </p>

            <!-- Creator identity -->
            <div
                v-if="socialAccount"
                class="flex items-center gap-3 rounded-lg bg-foreground/5 p-3"
            >
                <Avatar
                    :src="socialAccount.avatar_url"
                    :name="socialAccount.display_name"
                    class="size-9 shrink-0 rounded-full border-2 border-foreground shadow-2xs"
                />
                <div class="min-w-0 flex-1">
                    <p
                        class="text-[11px] font-black tracking-widest text-foreground/60 uppercase"
                    >
                        {{ $t('posts.form.tiktok.posting_to') }}
                    </p>
                    <p class="truncate text-sm">
                        <span class="font-bold text-foreground">{{
                            socialAccount.display_name
                        }}</span>
                        <span
                            v-if="socialAccount?.username"
                            class="font-medium text-foreground/60"
                            >&nbsp;@{{ socialAccount.username }}</span
                        >
                    </p>
                </div>
            </div>

            <!-- Privacy Level -->
            <div class="space-y-2">
                <Label
                    class="text-[11px] font-black tracking-widest text-foreground/60 uppercase"
                    >{{ $t('posts.form.tiktok.privacy_level') }}</Label
                >
                <Select v-model="privacyLevel" :disabled="props.disabled">
                    <SelectTrigger class="w-full">
                        <SelectValue
                            :placeholder="
                                $t('posts.form.tiktok.privacy_placeholder')
                            "
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in privacyOptions"
                            :key="option"
                            :value="option"
                        >
                            {{ $t(privacyLabelKey[option] ?? option) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-xs font-medium text-foreground/60">
                    {{ $t('posts.form.tiktok.privacy_hint') }}
                </p>
            </div>

            <!-- Max duration warning -->
            <p
                v-if="exceedsMaxDuration"
                class="flex items-start gap-2 rounded-lg border-2 border-foreground bg-rose-50 p-2 text-xs font-semibold text-rose-700"
            >
                <IconAlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                {{
                    $t('posts.form.tiktok.max_duration_exceeded', {
                        duration: String(videoDurationSec ?? 0),
                        max: String(maxDurationSec ?? 0),
                    })
                }}
            </p>

            <!-- Auto Add Music (photos only) -->
            <div class="space-y-2">
                <Label
                    class="text-[11px] font-black tracking-widest text-foreground/60 uppercase"
                    >{{ $t('posts.form.tiktok.auto_add_music') }}</Label
                >
                <Select v-model="autoAddMusic" :disabled="props.disabled">
                    <SelectTrigger class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="yes">{{
                            $t('posts.form.tiktok.yes')
                        }}</SelectItem>
                        <SelectItem value="no">{{
                            $t('posts.form.tiktok.no')
                        }}</SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-xs font-medium text-foreground/60">
                    {{ $t('posts.form.tiktok.auto_add_music_hint') }}
                </p>
            </div>

            <!-- Allow User To -->
            <div class="space-y-2">
                <Label
                    class="text-[11px] font-black tracking-widest text-foreground/60 uppercase"
                    >{{ $t('posts.form.tiktok.allow_users') }}</Label
                >
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                    <label
                        class="flex items-center gap-2 text-sm"
                        :class="{ 'opacity-50': commentDisabled }"
                        :title="
                            commentDisabled
                                ? $t(
                                      'posts.form.tiktok.interaction_disabled_by_creator',
                                  )
                                : ''
                        "
                    >
                        <Checkbox
                            v-model="allowComments"
                            :disabled="props.disabled || commentDisabled"
                        />
                        {{ $t('posts.form.tiktok.comments') }}
                    </label>
                    <label
                        class="flex items-center gap-2 text-sm"
                        :class="{ 'opacity-50': duetDisabled }"
                        :title="
                            duetDisabled
                                ? $t(
                                      'posts.form.tiktok.interaction_disabled_by_creator',
                                  )
                                : ''
                        "
                    >
                        <Checkbox
                            v-model="allowDuet"
                            :disabled="props.disabled || duetDisabled"
                        />
                        {{ $t('posts.form.tiktok.duet') }}
                    </label>
                    <label
                        class="flex items-center gap-2 text-sm"
                        :class="{ 'opacity-50': stitchDisabled }"
                        :title="
                            stitchDisabled
                                ? $t(
                                      'posts.form.tiktok.interaction_disabled_by_creator',
                                  )
                                : ''
                        "
                    >
                        <Checkbox
                            v-model="allowStitch"
                            :disabled="props.disabled || stitchDisabled"
                        />
                        {{ $t('posts.form.tiktok.stitch') }}
                    </label>
                </div>
            </div>

            <div class="border-t-2 border-foreground/10" />

            <!-- Video made with AI (independent) -->
            <label class="flex items-center gap-2 text-sm">
                <Checkbox v-model="isAigc" :disabled="props.disabled" />
                {{ $t('posts.form.tiktok.is_aigc') }}
            </label>

            <!-- Disclose video content (parent toggle) -->
            <div class="space-y-3">
                <label class="flex items-center gap-2 text-sm font-medium">
                    <Checkbox
                        v-model="discloseOpen"
                        :disabled="props.disabled"
                    />
                    {{ $t('posts.form.tiktok.disclose') }}
                </label>
                <p class="ml-6 text-xs font-medium text-foreground/60">
                    {{ $t('posts.form.tiktok.disclose_hint') }}
                </p>

                <!-- Promotional Content warning (shown once a sub-toggle is picked) -->
                <div
                    v-if="hasAnyBrandToggle"
                    class="ml-6 flex items-start gap-3 rounded-lg border-2 border-foreground bg-amber-100 p-3 shadow-2xs"
                >
                    <IconAlertTriangle
                        class="mt-0.5 size-4 shrink-0 text-amber-700"
                    />
                    <div class="text-xs text-amber-800">
                        <p class="font-bold">{{ $t(promotionalTitleKey) }}</p>
                        <p class="font-medium">
                            {{
                                $t('posts.form.tiktok.promotional_description')
                            }}
                        </p>
                    </div>
                </div>

                <!-- Compliance incomplete hint (disclose ON but no sub-toggle) -->
                <p
                    v-else-if="discloseOpen"
                    class="ml-6 text-xs font-semibold text-amber-700"
                >
                    {{ $t('posts.form.tiktok.compliance_incomplete') }}
                </p>

                <!-- Sub-toggles (only visible when disclose is on) -->
                <div v-if="discloseOpen" class="ml-6 space-y-3">
                    <div class="space-y-1">
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox
                                v-model="brandOrganicToggle"
                                :disabled="props.disabled"
                            />
                            {{ $t('posts.form.tiktok.brand_organic') }}
                        </label>
                        <p class="ml-6 text-xs font-medium text-foreground/60">
                            {{ $t('posts.form.tiktok.brand_organic_hint') }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox
                                v-model="brandContentToggle"
                                :disabled="props.disabled"
                            />
                            {{ $t('posts.form.tiktok.brand_content') }}
                        </label>
                        <p class="ml-6 text-xs font-medium text-foreground/60">
                            {{ $t('posts.form.tiktok.brand_content_hint') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Compliance declaration -->
            <p v-if="hasAnyBrandToggle" class="text-xs text-muted-foreground">
                {{ $t('posts.form.tiktok.compliance.agree') }}
                <a
                    :href="publishConfig?.musicUsageConfirmationUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="font-bold text-primary underline-offset-2 hover:underline"
                >
                    {{ $t('posts.form.tiktok.compliance.music_usage') }}
                </a>
                <template v-if="brandContentToggle">
                    {{ ' ' + $t('posts.form.tiktok.compliance.and') + ' ' }}
                    <a
                        :href="publishConfig?.brandedContentPolicyUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="font-bold text-primary underline-offset-2 hover:underline"
                    >
                        {{ $t('posts.form.tiktok.compliance.branded_policy') }}
                    </a>
                </template>
            </p>
        </div>
    </div>
</template>
