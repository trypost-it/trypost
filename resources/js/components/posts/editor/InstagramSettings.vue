<script setup lang="ts">
import { IconAlertTriangle, IconBrandInstagram, IconChevronDown, IconChevronUp } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import { Avatar } from '@/components/ui/avatar';
import { getMediaValidationWarning, type MediaItem } from '@/composables/useMedia';
import { ContentType } from '@/enums/content-type';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}

interface Props {
    socialAccount: SocialAccount | null;
    contentType: string;
    media: MediaItem[];
    meta?: Record<string, any>;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    meta: () => ({}),
});

const emit = defineEmits<{
    'update:contentType': [value: string];
    'update:meta': [meta: Record<string, any>];
}>();

const open = ref(false);

const variants = [
    { value: ContentType.InstagramFeed, labelKey: 'posts.form.instagram.variant.feed' },
    { value: ContentType.InstagramReel, labelKey: 'posts.form.instagram.variant.reel' },
    { value: ContentType.InstagramStory, labelKey: 'posts.form.instagram.variant.story' },
];

const aspectRatios = [
    { value: '1:1', labelKey: 'posts.form.instagram.aspect.square' },
    { value: '4:5', labelKey: 'posts.form.instagram.aspect.portrait' },
    { value: '16:9', labelKey: 'posts.form.instagram.aspect.landscape' },
    { value: 'original', labelKey: 'posts.form.instagram.aspect.original' },
];

const isFeed = computed(() => props.contentType === ContentType.InstagramFeed);
const selectedAspectRatio = computed(() => props.meta.aspect_ratio ?? '1:1');

const pickVariant = (value: string) => {
    if (props.disabled) return;
    emit('update:contentType', value);
};

const pickAspectRatio = (value: string) => {
    if (props.disabled) return;
    emit('update:meta', { ...props.meta, aspect_ratio: value });
};

const warning = computed(() => getMediaValidationWarning(props.contentType, props.media));
</script>

<template>
    <div class="rounded-lg border">
        <button
            type="button"
            class="flex w-full items-center justify-between p-4 text-sm font-medium"
            @click="open = !open"
        >
            <span class="flex items-center gap-2">
                <IconBrandInstagram class="size-5" />
                <span>{{ $t('posts.form.instagram.settings') }}</span>
                <span v-if="socialAccount" class="text-muted-foreground">·&nbsp;@{{ socialAccount.username }}</span>
            </span>
            <IconChevronUp v-if="open" class="h-4 w-4 text-muted-foreground" />
            <IconChevronDown v-else class="h-4 w-4 text-muted-foreground" />
        </button>

        <div v-if="open" class="space-y-5 border-t px-4 pb-4 pt-4">
            <div v-if="socialAccount" class="flex items-center gap-3 rounded-lg bg-muted/50 p-3">
                <Avatar
                    :src="socialAccount.avatar_url"
                    :name="socialAccount.display_name"
                    class="h-9 w-9 shrink-0 rounded-full"
                />
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-muted-foreground">{{ $t('posts.form.instagram.posting_to') }}</p>
                    <p class="truncate text-sm font-medium">
                        {{ socialAccount.display_name }}
                        <span class="text-muted-foreground">@{{ socialAccount.username }}</span>
                    </p>
                </div>
            </div>

            <div class="space-y-2">
                <p class="text-sm font-medium">{{ $t('posts.form.instagram.variant_label') }}</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="variant in variants"
                        :key="variant.value"
                        type="button"
                        class="rounded-full border px-3 py-1.5 text-xs transition-colors"
                        :class="contentType === variant.value
                            ? 'border-primary bg-primary/10 text-primary'
                            : 'border-border text-muted-foreground hover:text-foreground'"
                        :disabled="disabled"
                        @click="pickVariant(variant.value)"
                    >
                        {{ $t(variant.labelKey) }}
                    </button>
                </div>
            </div>

            <div v-if="isFeed" class="space-y-2">
                <p class="text-sm font-medium">{{ $t('posts.form.instagram.aspect_label') }}</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="ratio in aspectRatios"
                        :key="ratio.value"
                        type="button"
                        class="rounded-full border px-3 py-1.5 text-xs transition-colors"
                        :class="selectedAspectRatio === ratio.value
                            ? 'border-primary bg-primary/10 text-primary'
                            : 'border-border text-muted-foreground hover:text-foreground'"
                        :disabled="disabled"
                        @click="pickAspectRatio(ratio.value)"
                    >
                        {{ $t(ratio.labelKey) }}
                    </button>
                </div>
            </div>

            <p
                v-if="warning"
                class="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/5 p-2 text-xs text-destructive"
            >
                <IconAlertTriangle class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                {{ $t(`posts.form.warnings.${warning.key}`, warning.params) }}
            </p>
        </div>
    </div>
</template>
