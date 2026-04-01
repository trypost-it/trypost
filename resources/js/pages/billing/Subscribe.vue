<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { IconCheck } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { checkout } from '@/routes/app/billing';
import { index as workspacesIndex } from '@/routes/app/workspaces';

interface Props {
    trialDays: number;
}

const props = defineProps<Props>();

const processing = ref(false);
const displayDays = computed(() => props.trialDays - 1);

const subscribe = () => {
    processing.value = true;
    router.post(checkout.url());
};

const platforms = [
    { name: 'LinkedIn', icon: '/images/accounts/linkedin.png' },
    { name: 'X', icon: '/images/accounts/x.png' },
    { name: 'Instagram', icon: '/images/accounts/instagram.png' },
    { name: 'Facebook', icon: '/images/accounts/facebook.png' },
    { name: 'TikTok', icon: '/images/accounts/tiktok.png' },
    { name: 'YouTube', icon: '/images/accounts/youtube.png' },
    { name: 'Threads', icon: '/images/accounts/threads.png' },
    { name: 'Pinterest', icon: '/images/accounts/pinterest.png' },
    { name: 'Bluesky', icon: '/images/accounts/bluesky.png' },
    { name: 'Mastodon', icon: '/images/accounts/mastodon.png' },
];

const featureKeys = [
    'calendar',
    'scheduling',
    'media',
    'video',
    'team',
    'hashtags',
];
</script>

<template>
    <Head :title="$t('billing.subscribe.page_title')" />

    <div class="flex min-h-screen items-center justify-center bg-background px-4">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="mb-8 text-center">
                <img
                    src="/images/trypost/logo-light.png"
                    alt="TryPost"
                    class="mx-auto h-8 w-auto dark:hidden"
                />
                <img
                    src="/images/trypost/logo-dark.png"
                    alt="TryPost"
                    class="mx-auto hidden h-8 w-auto dark:block"
                />
            </div>

            <!-- Card -->
            <div class="rounded-xl border bg-card p-8">
                <div class="mb-6 text-center">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ $t('billing.subscribe.title') }}
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ trans('billing.subscribe.description', { days: String(displayDays) }) }}
                    </p>
                </div>

                <!-- Platforms -->
                <div class="mb-6 flex flex-wrap justify-center gap-2">
                    <img
                        v-for="platform in platforms"
                        :key="platform.name"
                        :src="platform.icon"
                        :alt="platform.name"
                        :title="platform.name"
                        class="size-10 rounded-full border bg-background p-0.5"
                    />
                </div>

                <Separator class="mb-6" />

                <!-- Features -->
                <ul class="mb-6 space-y-3">
                    <li
                        v-for="key in featureKeys"
                        :key="key"
                        class="flex items-center gap-3 text-sm"
                    >
                        <div class="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary/10">
                            <IconCheck class="size-3 text-primary" />
                        </div>
                        {{ $t(`billing.subscribe.features.${key}`) }}
                    </li>
                </ul>

                <!-- CTA -->
                <Button
                    class="w-full"
                    size="lg"
                    :disabled="processing"
                    @click="subscribe"
                >
                    {{ trans('billing.subscribe.start_trial', { days: String(displayDays) }) }}
                </Button>
            </div>

            <div class="mt-4 space-y-1 text-center">
                <p class="text-xs text-muted-foreground">
                    {{ $t('billing.subscribe.cancel_anytime') }}
                </p>
                <Link
                    :href="workspacesIndex.url()"
                    class="inline-block text-xs text-muted-foreground underline underline-offset-4 hover:text-foreground"
                >
                    {{ $t('billing.subscribe.switch_workspace') }}
                </Link>
            </div>
        </div>
    </div>
</template>
