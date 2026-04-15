<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3';
import { IconLoader2 } from '@tabler/icons-vue';
import { watch } from 'vue';

import { home } from '@/routes/app';

const props = defineProps<{
    subscriptionActive: boolean;
}>();

const { stop } = usePoll(2000, {
    only: ['subscriptionActive'],
});

watch(
    () => props.subscriptionActive,
    (active) => {
        if (active) {
            stop();
            router.visit(home.url());
        }
    },
    { immediate: true },
);
</script>

<template>
    <Head :title="$t('billing.processing.page_title')" />

    <div class="flex min-h-screen items-center justify-center bg-background">
        <div class="flex flex-col items-center gap-4 text-center">
            <IconLoader2 class="size-8 animate-spin text-muted-foreground" />
            <div>
                <h2 class="text-lg font-semibold tracking-tight">
                    {{ $t('billing.processing.title') }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ $t('billing.processing.description') }}
                </p>
            </div>
        </div>
    </div>
</template>
