<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import PhoneMockup from '@/components/PhoneMockup.vue';
import { PlatformPreview } from '@/components/posts/previews';
import { Avatar } from '@/components/ui/avatar';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { getContentTypeOptions, getPlatformLabel, getPlatformLogo } from '@/composables/usePlatformLogo';

interface MediaItem {
    id: string;
    path: string;
    url: string;
    type?: string;
    mime_type?: string;
    original_filename?: string;
}

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}

interface PostPlatform {
    id: string;
    platform: string;
    platform_name: string | null;
    platform_avatar: string | null;
    content_type: string | null;
    social_account: SocialAccount | null;
}

const props = defineProps<{
    platforms: PostPlatform[];
    content: string;
    media: MediaItem[];
    platformContentTypes: Record<string, string>;
    platformMeta?: Record<string, Record<string, any>>;
}>();

const emit = defineEmits<{
    'update:platformContentType': [platformId: string, contentType: string];
}>();

const getPlatformAvatar = (pp: PostPlatform): string | null => pp.social_account?.avatar_url ?? pp.platform_avatar ?? null;
const getPlatformDisplayName = (pp: PostPlatform): string => pp.social_account?.display_name ?? pp.platform_name ?? pp.platform;

const activeId = ref<string | null>(props.platforms[0]?.id ?? null);

watch(
    () => props.platforms,
    (next) => {
        if (!next.find((pp) => pp.id === activeId.value)) {
            activeId.value = next[0]?.id ?? null;
        }
    },
);

const activePlatform = computed(() => props.platforms.find((pp) => pp.id === activeId.value) ?? null);
const activeContentType = computed(() => {
    if (!activePlatform.value) return null;
    return props.platformContentTypes[activePlatform.value.id] ?? activePlatform.value.content_type;
});

const activeVariants = computed(() => {
    if (!activePlatform.value) return [];
    const options = getContentTypeOptions(activePlatform.value.platform);
    return options.length > 1 ? options : [];
});

const pickVariant = (value: string) => {
    if (!activePlatform.value) return;
    emit('update:platformContentType', activePlatform.value.id, value);
};
</script>

<template>
    <div class="flex h-full flex-col">
        <div v-if="platforms.length > 1" class="border-b px-4 py-3">
            <div class="flex flex-wrap gap-3">
                <TooltipProvider v-for="pp in platforms" :key="pp.id" :delay-duration="200">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <button
                                type="button"
                                class="relative transition-opacity"
                                :class="activeId === pp.id ? 'opacity-100' : 'opacity-40 hover:opacity-70'"
                                @click="activeId = pp.id"
                            >
                                <Avatar
                                    :src="getPlatformAvatar(pp)"
                                    :name="getPlatformDisplayName(pp)"
                                    class="h-9 w-9 shrink-0 rounded-full ring-2 ring-offset-2"
                                    :class="activeId === pp.id ? 'ring-primary' : 'ring-transparent'"
                                />
                                <img
                                    :src="getPlatformLogo(pp.platform)"
                                    :alt="pp.platform"
                                    class="absolute -bottom-1.5 -right-1.5 h-4 w-4 rounded-full bg-background object-contain ring-1 ring-border"
                                />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <div class="space-y-0.5 text-xs">
                                <p class="font-semibold">{{ getPlatformDisplayName(pp) }}<span v-if="pp.social_account?.username" class="font-normal opacity-80">&nbsp;·&nbsp;@{{ pp.social_account.username }}</span></p>
                                <p class="opacity-70">{{ getPlatformLabel(pp.platform) }}</p>
                            </div>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>
        </div>

        <div v-if="activeVariants.length > 0" class="border-b px-4 py-3">
            <div class="flex flex-wrap justify-center gap-2">
                <button
                    v-for="variant in activeVariants"
                    :key="variant.value"
                    type="button"
                    class="rounded-full border px-3 py-1.5 text-xs transition-colors"
                    :class="activeContentType === variant.value
                        ? 'border-primary bg-primary/10 text-primary'
                        : 'border-border text-muted-foreground hover:text-foreground'"
                    @click="pickVariant(variant.value)"
                >
                    {{ $t(variant.labelKey) }}
                </button>
            </div>
        </div>

        <div class="flex flex-1 justify-center bg-muted/30 px-4 py-8">
            <PhoneMockup v-if="activePlatform">
                <PlatformPreview
                    :platform="activePlatform.platform"
                    :content="content"
                    :media="media"
                    :social-account="activePlatform.social_account"
                    :content-type="activeContentType"
                    :meta="platformMeta?.[activePlatform.id] ?? {}"
                />
            </PhoneMockup>
        </div>
    </div>
</template>
