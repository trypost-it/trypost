<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { echo } from '@laravel/echo-vue';
import {
    IconArrowLeft,
    IconCheck,
    IconLoader2,
    IconRefresh,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onUnmounted, ref, watch } from 'vue';

import { start as startRoute } from '@/actions/App/Http/Controllers/App/PostAiCreateController';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { getPlatformLogo } from '@/composables/usePlatformLogo';
import { ContentType, type ContentTypeValue } from '@/enums/content-type';
import { edit as editPostRoute } from '@/routes/app/posts';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}

interface Props {
    socialAccounts: SocialAccount[];
    /** ISO date (YYYY-MM-DD) carried over from the calendar's per-day "+" button. */
    date?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    date: null,
});

type WizardStep = 'configure' | 'generating';

const emit = defineEmits<{
    /** Parent mirrors this in the PageHeader for context. */
    'update:stepHeader': [{ title: string; description: string }];
    /** Back button on the configure step asks parent to leave the AI flow. */
    cancel: [];
}>();

const step = ref<WizardStep>('configure');

// Selections
const selectedFormat = ref<ContentTypeValue | null>(null);
const selectedAccountId = ref<string | null>(null);
const includeImages = ref(true);
const imageCount = ref(2);
const promptText = ref('');

// Generation state
const submitting = ref(false);
const generationStatus = ref<'loading' | 'error'>('loading');
const generationError = ref('');
let echoChannel: any = null;
let subscribedChannelName: string | null = null;

const httpStart = useHttp<{
    format: string | null;
    social_account_id: string | null;
    image_count: number;
    prompt: string;
    date: string | null;
}>({ format: null, social_account_id: null, image_count: 0, prompt: '', date: null });

const AI_FORMATS: Array<{ value: ContentTypeValue; platforms: string[] }> = [
    { value: ContentType.InstagramFeed, platforms: ['instagram', 'instagram-facebook'] },
    { value: ContentType.InstagramCarousel, platforms: ['instagram', 'instagram-facebook'] },
    { value: ContentType.InstagramStory, platforms: ['instagram', 'instagram-facebook'] },
    { value: ContentType.LinkedInPost, platforms: ['linkedin'] },
    { value: ContentType.LinkedInPagePost, platforms: ['linkedin-page'] },
    { value: ContentType.XPost, platforms: ['x'] },
    { value: ContentType.BlueskyPost, platforms: ['bluesky'] },
    { value: ContentType.ThreadsPost, platforms: ['threads'] },
    { value: ContentType.MastodonPost, platforms: ['mastodon'] },
    { value: ContentType.FacebookPost, platforms: ['facebook'] },
    { value: ContentType.FacebookStory, platforms: ['facebook'] },
    { value: ContentType.PinterestPin, platforms: ['pinterest'] },
];

const connectedPlatforms = computed(() => {
    const platforms = new Set<string>();
    for (const account of props.socialAccounts) {
        platforms.add(account.platform);
    }
    return Array.from(platforms);
});

// Show ALL formats — disabled when the workspace has no connected account
// for that platform. Filtering them out hides the catalog from the user.
const availableFormats = computed(() => AI_FORMATS);

const isFormatConnected = (format: typeof AI_FORMATS[number]): boolean =>
    format.platforms.some((p) => connectedPlatforms.value.includes(p));

const accountsForFormat = computed(() => {
    if (!selectedFormat.value) return [];
    const format = AI_FORMATS.find((f) => f.value === selectedFormat.value);
    if (!format) return [];
    return props.socialAccounts.filter((a) => format.platforms.includes(a.platform));
});

const isCarousel = computed(() => selectedFormat.value === ContentType.InstagramCarousel);
const requiresImage = computed(() =>
    selectedFormat.value === ContentType.FacebookPost ||
    selectedFormat.value === ContentType.PinterestPin ||
    selectedFormat.value === ContentType.InstagramStory ||
    selectedFormat.value === ContentType.FacebookStory,
);
const supportsOptionalImages = computed(() =>
    selectedFormat.value === ContentType.InstagramFeed ||
    selectedFormat.value === ContentType.LinkedInPost ||
    selectedFormat.value === ContentType.LinkedInPagePost ||
    selectedFormat.value === ContentType.XPost ||
    selectedFormat.value === ContentType.BlueskyPost ||
    selectedFormat.value === ContentType.ThreadsPost ||
    selectedFormat.value === ContentType.MastodonPost,
);
// Instagram Feed accepts only 1 image (single-image post). Others accept up to 4.
const maxOptionalImages = computed(() =>
    selectedFormat.value === ContentType.InstagramFeed ? 1 : 4,
);
// Mirrors ContentType::supportsCaption() in PHP.
const supportsCaption = computed(() =>
    selectedFormat.value !== ContentType.InstagramStory &&
    selectedFormat.value !== ContentType.FacebookStory,
);
const showsAccountPicker = computed(() => accountsForFormat.value.length > 1);

const submittedImageCount = computed(() => {
    if (isCarousel.value) return imageCount.value;
    if (requiresImage.value) return 1;
    if (supportsOptionalImages.value && includeImages.value) return imageCount.value;
    return 0;
});

const canSubmit = computed(() =>
    selectedFormat.value !== null &&
    selectedAccountId.value !== null &&
    promptText.value.trim().length >= 3,
);

// Auto-pick the only account when format has exactly one match.
watch(accountsForFormat, (accounts) => {
    if (accounts.length === 1) {
        selectedAccountId.value = accounts[0].id;
    } else if (accounts.length === 0) {
        selectedAccountId.value = null;
    } else if (accounts.length > 1 && !accounts.some((a) => a.id === selectedAccountId.value)) {
        selectedAccountId.value = null;
    }
});

const selectFormat = (format: ContentTypeValue) => {
    selectedFormat.value = format;
    // Sensible default per format. Picking a format always pre-selects an
    // image option so the user sees a chip highlighted on arrival.
    if (format === ContentType.InstagramCarousel) {
        imageCount.value = 5;
    } else if (format === ContentType.InstagramFeed) {
        imageCount.value = 1;
        includeImages.value = true;
    } else {
        imageCount.value = 2;
        includeImages.value = true;
    }
};

// Step header text — the parent reflects this in the PageHeader.
const stepHeaderFor = (s: WizardStep) => {
    switch (s) {
        case 'configure':
            return {
                title: trans('posts.create.ai_title'),
                description: trans('posts.create.ai_configure_description'),
            };
        case 'generating':
            return {
                title: trans('posts.create.steps.generating_title'),
                description: '',
            };
    }
};

const goToStep = (s: WizardStep) => {
    step.value = s;
    emit('update:stepHeader', stepHeaderFor(s));
};

emit('update:stepHeader', stepHeaderFor(step.value));

const goBack = () => {
    if (step.value === 'configure') {
        emit('cancel');
    } else if (step.value === 'generating') {
        unsubscribeEcho();
        goToStep('configure');
    }
};

const unsubscribeEcho = () => {
    if (echoChannel && subscribedChannelName) {
        echo().leave(`private-${subscribedChannelName}`);
        echoChannel = null;
        subscribedChannelName = null;
    }
};

const subscribeToCreation = (userId: string, creationId: string) => {
    unsubscribeEcho();
    const channelName = `users.${userId}.ai-creation.${creationId}`;
    subscribedChannelName = channelName;

    echoChannel = echo().private(channelName).listen('.ai.creation.completed', (e: any) => {
        unsubscribeEcho();
        if (e.error || !e.post_id) {
            generationStatus.value = 'error';
            generationError.value = e.error ?? trans('posts.create.steps.preview_error');
            return;
        }
        router.visit(editPostRoute(e.post_id).url);
    });
};

const startGeneration = async () => {
    if (!canSubmit.value || submitting.value) return;

    submitting.value = true;
    generationStatus.value = 'loading';
    generationError.value = '';
    goToStep('generating');

    httpStart.format = selectedFormat.value;
    httpStart.social_account_id = selectedAccountId.value;
    httpStart.image_count = submittedImageCount.value;
    httpStart.prompt = promptText.value.trim();
    httpStart.date = props.date;

    try {
        const data = await httpStart.post(startRoute.url()) as { creation_id: string; channel: string };
        const userId = data.channel.split('.')[1] ?? '';
        subscribeToCreation(userId, data.creation_id);
    } catch (err: any) {
        generationStatus.value = 'error';
        generationError.value = err?.response?.data?.message ?? trans('posts.create.steps.preview_error');
    } finally {
        submitting.value = false;
    }
};

const retryGeneration = () => startGeneration();

onUnmounted(() => unsubscribeEcho());
</script>

<template>
    <div class="space-y-6">
        <!-- Back button — sticker arrow + ink label, mirrors the marketing site -->
        <button
            v-if="step !== 'generating' || generationStatus === 'error'"
            type="button"
            class="group inline-flex cursor-pointer items-center gap-1.5 text-sm font-semibold text-foreground/70 transition-colors hover:text-foreground"
            @click="goBack"
        >
            <span class="inline-flex size-7 items-center justify-center rounded-md border-2 border-foreground bg-card shadow-2xs transition-transform group-hover:-translate-x-0.5">
                <IconArrowLeft class="size-3.5 text-foreground" stroke-width="2.5" />
            </span>
            {{ $t('posts.create.steps.back') }}
        </button>

        <!-- ====== Step 1: Configure (everything in one screen) ====== -->
        <template v-if="step === 'configure'">
            <!-- Format -->
            <div class="space-y-2">
                <Label class="text-sm font-bold">{{ $t('posts.create.steps.format_title') }}</Label>
                <div class="grid gap-2 sm:grid-cols-2">
                    <button
                        v-for="format in availableFormats"
                        :key="format.value"
                        type="button"
                        class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-foreground bg-card p-3.5 text-left text-sm shadow-2xs transition-all hover:bg-foreground/5 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-card"
                        :class="{ '!bg-violet-100 shadow-md': selectedFormat === format.value }"
                        :disabled="!isFormatConnected(format)"
                        :title="!isFormatConnected(format) ? $t('posts.create.steps.connect_first') : ''"
                        @click="selectFormat(format.value)"
                    >
                        <span class="inline-flex size-7 items-center justify-center overflow-hidden rounded-full border-2 border-foreground bg-card shadow-2xs">
                            <img
                                :src="getPlatformLogo(format.platforms[0])"
                                :alt="format.platforms[0]"
                                class="size-full object-cover"
                            />
                        </span>
                        <span class="flex-1 font-semibold text-foreground">{{ $t(`posts.create.steps.format.${format.value}`) }}</span>
                        <IconCheck v-if="selectedFormat === format.value" class="size-4 text-foreground" stroke-width="3" />
                    </button>
                </div>
            </div>

            <!-- Account (only when there's a choice to make) -->
            <div v-if="selectedFormat && showsAccountPicker" class="space-y-2">
                <Label class="text-sm font-bold">{{ $t('posts.create.steps.account_title') }}</Label>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <button
                        v-for="account in accountsForFormat"
                        :key="account.id"
                        type="button"
                        class="relative flex cursor-pointer items-center gap-2 rounded-xl border-2 border-foreground bg-card p-2.5 text-left text-sm shadow-2xs transition-all hover:bg-foreground/5"
                        :class="{ '!bg-violet-100 shadow-md': selectedAccountId === account.id }"
                        @click="selectedAccountId = account.id"
                    >
                        <span class="inline-flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-foreground bg-card shadow-2xs">
                            <img
                                v-if="account.avatar_url"
                                :src="account.avatar_url"
                                :alt="account.display_name"
                                class="size-full object-cover"
                            />
                            <img v-else :src="getPlatformLogo(account.platform)" :alt="account.platform" class="size-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-bold leading-tight text-foreground">{{ account.display_name }}</p>
                            <p v-if="account.username" class="truncate text-xs font-medium text-foreground/60">@{{ account.username }}</p>
                        </div>
                        <IconCheck v-if="selectedAccountId === account.id" class="absolute right-2 top-2 size-3.5 text-foreground" stroke-width="3" />
                    </button>
                </div>
            </div>

            <!-- Media — inline, only when format actually has options -->
            <div v-if="selectedFormat && isCarousel" class="space-y-2">
                <Label class="text-sm font-bold">{{ $t('posts.create.steps.media_carousel') }}</Label>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="n in [2, 3, 4, 5, 6, 7, 8, 9, 10]"
                        :key="n"
                        type="button"
                        size="icon"
                        :variant="imageCount === n ? 'default' : 'outline'"
                        @click="imageCount = n"
                    >
                        {{ n }}
                    </Button>
                </div>
            </div>

            <div v-if="selectedFormat && supportsOptionalImages" class="space-y-2">
                <Label class="text-sm font-bold">{{ $t('posts.create.steps.media_optional_label') }}</Label>
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        :variant="!includeImages ? 'default' : 'outline'"
                        @click="includeImages = false"
                    >
                        {{ $t('posts.create.steps.media_none') }}
                    </Button>
                    <Button
                        v-for="n in maxOptionalImages"
                        :key="n"
                        type="button"
                        size="icon"
                        :variant="includeImages && imageCount === n ? 'default' : 'outline'"
                        @click="includeImages = true; imageCount = n"
                    >
                        {{ n }}
                    </Button>
                </div>
            </div>

            <!-- Prompt -->
            <div v-if="selectedFormat" class="space-y-2">
                <Label for="ai-prompt" class="text-sm font-bold">{{ $t('posts.create.steps.prompt_label') }}</Label>
                <Textarea
                    id="ai-prompt"
                    v-model="promptText"
                    :placeholder="$t('posts.create.steps.prompt_placeholder')"
                    class="min-h-[140px] resize-none"
                />
            </div>

            <!-- Generate -->
            <div v-if="selectedFormat" class="flex justify-end pt-1">
                <Button :disabled="!canSubmit" @click="startGeneration">
                    {{ $t('posts.ai.generate.start') }}
                </Button>
            </div>
        </template>

        <!-- ====== Step 2: Generating ====== -->
        <template v-else-if="step === 'generating'">
            <div v-if="generationStatus === 'loading'" class="flex flex-col items-center gap-4 rounded-2xl border-2 border-foreground bg-card py-16 text-center shadow-2xs">
                <div class="inline-flex size-12 -rotate-2 items-center justify-center rounded-2xl border-2 border-foreground bg-violet-200 shadow-2xs">
                    <IconLoader2 class="size-6 animate-spin text-foreground" stroke-width="2" />
                </div>
                <p class="text-sm font-semibold text-foreground/70">{{ $t('posts.create.steps.generation_loading') }}</p>
            </div>

            <div v-else class="space-y-4">
                <div class="rounded-xl border-2 border-foreground bg-rose-50 p-4 shadow-2xs">
                    <p class="text-sm font-semibold text-rose-700">{{ generationError || $t('posts.create.steps.preview_error') }}</p>
                </div>

                <div class="flex justify-end">
                    <Button variant="outline" @click="retryGeneration">
                        <IconRefresh class="size-4" />
                        {{ $t('posts.create.steps.retry') }}
                    </Button>
                </div>
            </div>
        </template>
    </div>
</template>
