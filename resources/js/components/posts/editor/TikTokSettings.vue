<script setup lang="ts">
import { IconAlertTriangle, IconBrandTiktok, IconChevronDown, IconChevronUp } from '@tabler/icons-vue';
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

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}

interface PublishConfig {
    privacyLevelOptions: string[];
    musicUsageConfirmationUrl: string;
    brandedContentPolicyUrl: string;
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
    publishConfig: PublishConfig | null;
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
    get: () => (props.meta?.auto_add_music ?? false) ? 'yes' : 'no',
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
        updateMeta({ disclose: false, brand_organic_toggle: false, brand_content_toggle: false });
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
    return fromApi.length > 0 ? fromApi : props.publishConfig?.privacyLevelOptions ?? [];
});

// Branded content cannot be private (TikTok compliance).
const privacyOptions = computed(() =>
    brandContentToggle.value
        ? allPrivacyOptions.value.filter((o) => o !== 'SELF_ONLY')
        : allPrivacyOptions.value,
);

const commentDisabled = computed(() => Boolean(props.creatorInfo?.comment_disabled));
const duetDisabled = computed(() => Boolean(props.creatorInfo?.duet_disabled));
const stitchDisabled = computed(() => Boolean(props.creatorInfo?.stitch_disabled));

// Max video duration check (when creator_info is available and we have duration).
const maxDurationSec = computed(() => props.creatorInfo?.max_video_post_duration_sec ?? null);
const exceedsMaxDuration = computed(() => {
    if (maxDurationSec.value === null || props.videoDurationSec === null) return false;
    return props.videoDurationSec > maxDurationSec.value;
});

const privacyLabelKey: Record<string, string> = {
    PUBLIC_TO_EVERYONE: 'posts.form.tiktok.privacy.public',
    MUTUAL_FOLLOW_FRIENDS: 'posts.form.tiktok.privacy.friends',
    FOLLOWER_OF_CREATOR: 'posts.form.tiktok.privacy.followers',
    SELF_ONLY: 'posts.form.tiktok.privacy.private',
};

const hasAnyBrandToggle = computed(() => brandOrganicToggle.value || brandContentToggle.value);

// Label required by TikTok: "Paid partnership" when branded content (with or without organic),
// otherwise "Promotional content".
const promotionalTitleKey = computed(() =>
    brandContentToggle.value ? 'posts.form.tiktok.promotional_paid_title' : 'posts.form.tiktok.promotional_organic_title',
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
    () => [commentDisabled.value, duetDisabled.value, stitchDisabled.value] as const,
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
    <div class="rounded-lg border">
        <button
            type="button"
            class="flex w-full items-center justify-between p-4 text-sm font-medium"
            @click="open = !open"
        >
            <span class="flex items-center gap-2">
                <IconBrandTiktok class="h-4 w-4" />
                <span>{{ $t('posts.form.tiktok.settings') }}</span>
                <span v-if="socialAccount" class="text-muted-foreground">·&nbsp;@{{ socialAccount.username }}</span>
            </span>
            <IconChevronUp v-if="open" class="h-4 w-4 text-muted-foreground" />
            <IconChevronDown v-else class="h-4 w-4 text-muted-foreground" />
        </button>

        <div v-if="open" class="space-y-5 border-t px-4 pb-4 pt-4">
            <p v-if="creatorInfoLoading" class="flex items-center gap-2 text-xs text-muted-foreground">
                <span class="inline-block h-3 w-3 animate-pulse rounded-full bg-muted" />
                {{ $t('posts.form.tiktok.creator_info_loading') }}
            </p>

            <!-- Creator identity -->
            <div v-if="socialAccount" class="flex items-center gap-3 rounded-lg bg-muted/50 p-3">
                <Avatar
                    :src="socialAccount.avatar_url"
                    :name="socialAccount.display_name"
                    class="h-9 w-9 shrink-0 rounded-full"
                />
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-muted-foreground">{{ $t('posts.form.tiktok.posting_to') }}</p>
                    <p class="truncate text-sm font-medium">
                        {{ socialAccount.display_name }}
                        <span class="text-muted-foreground">@{{ socialAccount.username }}</span>
                    </p>
                </div>
            </div>

            <!-- Privacy Level -->
            <div class="space-y-2">
                <Label class="text-sm font-medium">{{ $t('posts.form.tiktok.privacy_level') }}</Label>
                <Select v-model="privacyLevel" :disabled="props.disabled">
                    <SelectTrigger class="w-full">
                        <SelectValue :placeholder="$t('posts.form.tiktok.privacy_placeholder')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="option in privacyOptions" :key="option" :value="option">
                            {{ $t(privacyLabelKey[option] ?? option) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-xs text-muted-foreground">{{ $t('posts.form.tiktok.privacy_hint') }}</p>
            </div>

            <!-- Max duration warning -->
            <p v-if="exceedsMaxDuration" class="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/5 p-2 text-xs text-destructive">
                <IconAlertTriangle class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                {{ $t('posts.form.tiktok.max_duration_exceeded', { duration: videoDurationSec ?? 0, max: maxDurationSec ?? 0 }) }}
            </p>

            <!-- Auto Add Music (photos only) -->
            <div class="space-y-2">
                <Label class="text-sm font-medium">{{ $t('posts.form.tiktok.auto_add_music') }}</Label>
                <Select v-model="autoAddMusic" :disabled="props.disabled">
                    <SelectTrigger class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="yes">{{ $t('posts.form.tiktok.yes') }}</SelectItem>
                        <SelectItem value="no">{{ $t('posts.form.tiktok.no') }}</SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-xs text-muted-foreground">{{ $t('posts.form.tiktok.auto_add_music_hint') }}</p>
            </div>

            <!-- Allow User To -->
            <div class="space-y-2">
                <Label class="text-sm font-medium">{{ $t('posts.form.tiktok.allow_users') }}</Label>
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                    <label class="flex items-center gap-2 text-sm" :class="{ 'opacity-50': commentDisabled }" :title="commentDisabled ? $t('posts.form.tiktok.interaction_disabled_by_creator') : ''">
                        <Checkbox v-model="allowComments" :disabled="props.disabled || commentDisabled" />
                        {{ $t('posts.form.tiktok.comments') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm" :class="{ 'opacity-50': duetDisabled }" :title="duetDisabled ? $t('posts.form.tiktok.interaction_disabled_by_creator') : ''">
                        <Checkbox v-model="allowDuet" :disabled="props.disabled || duetDisabled" />
                        {{ $t('posts.form.tiktok.duet') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm" :class="{ 'opacity-50': stitchDisabled }" :title="stitchDisabled ? $t('posts.form.tiktok.interaction_disabled_by_creator') : ''">
                        <Checkbox v-model="allowStitch" :disabled="props.disabled || stitchDisabled" />
                        {{ $t('posts.form.tiktok.stitch') }}
                    </label>
                </div>
            </div>

            <div class="border-t" />

            <!-- Video made with AI (independent) -->
            <label class="flex items-center gap-2 text-sm">
                <Checkbox v-model="isAigc" :disabled="props.disabled" />
                {{ $t('posts.form.tiktok.is_aigc') }}
            </label>

            <!-- Disclose video content (parent toggle) -->
            <div class="space-y-3">
                <label class="flex items-center gap-2 text-sm font-medium">
                    <Checkbox v-model="discloseOpen" :disabled="props.disabled" />
                    {{ $t('posts.form.tiktok.disclose') }}
                </label>
                <p class="ml-6 text-xs text-muted-foreground">{{ $t('posts.form.tiktok.disclose_hint') }}</p>

                <!-- Promotional Content warning (shown once a sub-toggle is picked) -->
                <div v-if="hasAnyBrandToggle" class="ml-6 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/40 dark:bg-amber-950/30">
                    <IconAlertTriangle class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                    <div class="text-xs text-amber-800 dark:text-amber-200">
                        <p class="font-medium">{{ $t(promotionalTitleKey) }}</p>
                        <p>{{ $t('posts.form.tiktok.promotional_description') }}</p>
                    </div>
                </div>

                <!-- Compliance incomplete hint (disclose ON but no sub-toggle) -->
                <p v-else-if="discloseOpen" class="ml-6 text-xs text-amber-600 dark:text-amber-400">
                    {{ $t('posts.form.tiktok.compliance_incomplete') }}
                </p>

                <!-- Sub-toggles (only visible when disclose is on) -->
                <div v-if="discloseOpen" class="ml-6 space-y-3">
                    <div class="space-y-1">
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox v-model="brandOrganicToggle" :disabled="props.disabled" />
                            {{ $t('posts.form.tiktok.brand_organic') }}
                        </label>
                        <p class="ml-6 text-xs text-muted-foreground">{{ $t('posts.form.tiktok.brand_organic_hint') }}</p>
                    </div>
                    <div class="space-y-1">
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox v-model="brandContentToggle" :disabled="props.disabled" />
                            {{ $t('posts.form.tiktok.brand_content') }}
                        </label>
                        <p class="ml-6 text-xs text-muted-foreground">{{ $t('posts.form.tiktok.brand_content_hint') }}</p>
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
                    class="text-primary underline-offset-2 hover:underline"
                >
                    {{ $t('posts.form.tiktok.compliance.music_usage') }}
                </a>
                <template v-if="brandContentToggle">
                    {{ ' ' + $t('posts.form.tiktok.compliance.and') + ' ' }}
                    <a
                        :href="publishConfig?.brandedContentPolicyUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-primary underline-offset-2 hover:underline"
                    >
                        {{ $t('posts.form.tiktok.compliance.branded_policy') }}
                    </a>
                </template>
            </p>
        </div>
    </div>
</template>
