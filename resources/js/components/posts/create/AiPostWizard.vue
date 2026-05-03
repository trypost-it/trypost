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

import { finalize as finalizeRoute, start as startRoute } from '@/actions/App/Http/Controllers/App/PostAiCreateController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { getPlatformLogo } from '@/composables/usePlatformLogo';
import { ContentType, type ContentTypeValue } from '@/enums/content-type';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}

interface Props {
    socialAccounts: SocialAccount[];
}

const props = defineProps<Props>();

type WizardStep = 'configure' | 'preview';

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

// Preview state
const submitting = ref(false);
const finalizing = ref(false);
const previewStatus = ref<'loading' | 'done' | 'error'>('loading');
const previewContent = ref('');
const previewImageTitle = ref('');
const previewImageBody = ref('');
const previewError = ref('');
const previewCreationId = ref<string | null>(null);
let echoChannel: any = null;
let subscribedChannelName: string | null = null;

const httpStart = useHttp<{
    format: string | null;
    social_account_id: string | null;
    image_count: number;
    prompt: string;
}>({ format: null, social_account_id: null, image_count: 0, prompt: '' });

const httpFinalize = useHttp<{ content: string; image_title: string; image_body: string }>({
    content: '',
    image_title: '',
    image_body: '',
});

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
        case 'preview':
            return {
                title: trans('posts.create.steps.preview_title'),
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
    } else if (step.value === 'preview') {
        goToStep('configure');
    }
};

// Echo subscription for AI streaming
const unsubscribeEcho = () => {
    if (echoChannel && subscribedChannelName) {
        echo().leave(`private-${subscribedChannelName}`);
        echoChannel = null;
        subscribedChannelName = null;
    }
};

const subscribeToCreation = (userId: string, creationId: string) => {
    unsubscribeEcho();
    previewCreationId.value = creationId;
    const channelName = `users.${userId}.ai-creation.${creationId}`;
    subscribedChannelName = channelName;

    echoChannel = echo().private(channelName).listen('.PostCreationReady', (e: any) => {
        if (e.error) {
            previewStatus.value = 'error';
            previewError.value = e.error;
        } else {
            previewContent.value = e.content ?? '';
            previewImageTitle.value = e.image_title ?? '';
            previewImageBody.value = e.image_body ?? '';
            previewStatus.value = 'done';
        }
        unsubscribeEcho();
    });
};

const startGeneration = async () => {
    if (!canSubmit.value || submitting.value) return;

    submitting.value = true;
    previewStatus.value = 'loading';
    previewContent.value = '';
    previewImageTitle.value = '';
    previewImageBody.value = '';
    previewError.value = '';
    goToStep('preview');

    httpStart.format = selectedFormat.value;
    httpStart.social_account_id = selectedAccountId.value;
    httpStart.image_count = submittedImageCount.value;
    httpStart.prompt = promptText.value.trim();

    try {
        const data = await httpStart.post(startRoute.url()) as { creation_id: string; channel: string };
        const userId = data.channel.split('.')[1] ?? '';
        subscribeToCreation(userId, data.creation_id);
    } catch (err: any) {
        previewStatus.value = 'error';
        previewError.value = err?.response?.data?.message ?? trans('posts.create.steps.preview_error');
    } finally {
        submitting.value = false;
    }
};

const retryGeneration = () => startGeneration();

const createPost = async () => {
    if (!previewCreationId.value || finalizing.value) return;
    finalizing.value = true;

    httpFinalize.content = previewContent.value;
    httpFinalize.image_title = previewImageTitle.value;
    httpFinalize.image_body = previewImageBody.value;

    try {
        const data = await httpFinalize.post(finalizeRoute.url(previewCreationId.value)) as { redirect_url: string };
        router.visit(data.redirect_url);
    } catch {
        previewStatus.value = 'error';
        previewError.value = trans('posts.create.steps.preview_error');
    } finally {
        finalizing.value = false;
    }
};

onUnmounted(() => unsubscribeEcho());
</script>

<template>
    <div class="space-y-6">
        <!-- Back button — consistent across both steps -->
        <div class="flex items-center">
            <Button variant="ghost" size="sm" class="-ml-2 text-muted-foreground" @click="goBack">
                <IconArrowLeft class="mr-1 size-4" />
                {{ $t('posts.create.steps.back') }}
            </Button>
        </div>

        <!-- ====== Step 1: Configure (everything in one screen) ====== -->
        <template v-if="step === 'configure'">
            <!-- Format -->
            <div class="space-y-2">
                <Label class="text-sm font-medium">{{ $t('posts.create.steps.format_title') }}</Label>
                <div class="grid gap-2 sm:grid-cols-2">
                    <button
                        v-for="format in availableFormats"
                        :key="format.value"
                        type="button"
                        class="flex items-center gap-3 rounded-xl border bg-card p-3.5 text-left text-sm transition-all hover:border-primary/50 hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:border-border disabled:hover:bg-card"
                        :class="{ 'border-primary bg-primary/5 ring-1 ring-primary/30': selectedFormat === format.value }"
                        :disabled="!isFormatConnected(format)"
                        :title="!isFormatConnected(format) ? $t('posts.create.steps.connect_first') : ''"
                        @click="selectFormat(format.value)"
                    >
                        <img
                            :src="getPlatformLogo(format.platforms[0])"
                            :alt="format.platforms[0]"
                            class="size-6 rounded-full ring-1 ring-background"
                        />
                        <span class="flex-1 font-medium">{{ $t(`posts.create.steps.format.${format.value}`) }}</span>
                        <IconCheck v-if="selectedFormat === format.value" class="size-4 text-primary" />
                    </button>
                </div>
            </div>

            <!-- Account (only when there's a choice to make) -->
            <div v-if="selectedFormat && showsAccountPicker" class="space-y-2">
                <Label class="text-sm font-medium">{{ $t('posts.create.steps.account_title') }}</Label>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <button
                        v-for="account in accountsForFormat"
                        :key="account.id"
                        type="button"
                        class="relative flex items-center gap-2 rounded-xl border bg-card p-2.5 text-left text-sm transition-all hover:border-primary/50 hover:bg-primary/5"
                        :class="{ 'border-primary bg-primary/5 ring-1 ring-primary/30': selectedAccountId === account.id }"
                        @click="selectedAccountId = account.id"
                    >
                        <img
                            v-if="account.avatar_url"
                            :src="account.avatar_url"
                            :alt="account.display_name"
                            class="size-8 shrink-0 rounded-full"
                        />
                        <div v-else class="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted">
                            <img :src="getPlatformLogo(account.platform)" :alt="account.platform" class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-medium leading-tight">{{ account.display_name }}</p>
                            <p v-if="account.username" class="truncate text-xs text-muted-foreground">@{{ account.username }}</p>
                        </div>
                        <IconCheck v-if="selectedAccountId === account.id" class="absolute right-2 top-2 size-3.5 text-primary" />
                    </button>
                </div>
            </div>

            <!-- Media — inline, only when format actually has options -->
            <div v-if="selectedFormat && isCarousel" class="space-y-2">
                <Label class="text-sm font-medium">{{ $t('posts.create.steps.media_carousel') }}</Label>
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
                <Label class="text-sm font-medium">{{ $t('posts.create.steps.media_optional_label') }}</Label>
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
                <Label for="ai-prompt" class="text-sm font-medium">{{ $t('posts.create.steps.prompt_label') }}</Label>
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

        <!-- ====== Step 2: Preview ====== -->
        <template v-else-if="step === 'preview'">
            <div v-if="previewStatus === 'loading'" class="flex flex-col items-center gap-4 rounded-xl border bg-muted/20 py-16 text-center">
                <IconLoader2 class="size-10 animate-spin text-primary" />
                <p class="text-sm text-muted-foreground">{{ $t('posts.create.steps.preview_loading') }}</p>
            </div>

            <div v-else-if="previewStatus === 'error'" class="space-y-4">
                <div class="rounded-xl border border-destructive/30 bg-destructive/5 p-4">
                    <p class="text-sm text-destructive">{{ previewError || $t('posts.create.steps.preview_error') }}</p>
                </div>

                <div class="flex justify-end">
                    <Button variant="outline" @click="retryGeneration">
                        <IconRefresh class="mr-1 size-4" />
                        {{ $t('posts.create.steps.retry') }}
                    </Button>
                </div>
            </div>

            <div v-else-if="previewStatus === 'done'" class="space-y-4">
                <!-- Caption-less formats (Stories): edit title + body separately. -->
                <div v-if="!supportsCaption" class="space-y-3 rounded-xl border bg-muted/20 p-5">
                    <div class="space-y-1">
                        <Label class="text-xs font-medium text-muted-foreground">{{ $t('posts.create.preview.image_title') }}</Label>
                        <Input v-model="previewImageTitle" class="bg-background" />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs font-medium text-muted-foreground">{{ $t('posts.create.preview.image_body') }}</Label>
                        <Textarea v-model="previewImageBody" class="min-h-[120px] resize-none bg-background" />
                    </div>
                </div>

                <!-- Default: edit caption text. -->
                <Textarea
                    v-else
                    v-model="previewContent"
                    class="min-h-[200px] resize-none rounded-xl border bg-muted/20 p-5 text-sm leading-relaxed"
                />

                <div class="flex justify-end gap-2">
                    <Button variant="outline" size="sm" @click="retryGeneration">
                        <IconRefresh class="mr-1 size-4" />
                        {{ $t('posts.create.steps.retry') }}
                    </Button>
                    <Button :disabled="finalizing" @click="createPost">
                        <IconLoader2 v-if="finalizing" class="mr-1 size-4 animate-spin" />
                        {{ $t('posts.create.steps.create') }}
                    </Button>
                </div>
            </div>
        </template>
    </div>
</template>
