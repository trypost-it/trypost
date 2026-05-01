<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { onMounted, onUnmounted } from 'vue';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { getPlatformLogo } from '@/composables/usePlatformLogo';

export interface AvailablePlatform {
    value: string;
    label: string;
    color: string;
}

defineProps<{
    platforms: AvailablePlatform[];
}>();

const open = defineModel<boolean>('open', { default: false });

const getPlatformDescription = (platform: string): string => {
    return trans(`accounts.descriptions.${platform}`);
};

const openOAuthPopup = (platformValue: string) => {
    const url = `/connect/${platformValue}`;
    const width = 600;
    const height = 700;
    const left = window.screenX + (window.outerWidth - width) / 2;
    const top = window.screenY + (window.outerHeight - height) / 2;

    open.value = false;

    window.open(
        url,
        'oauth-popup',
        `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`,
    );
};

const handleOAuthMessage = (event: MessageEvent) => {
    if (event.origin !== window.location.origin) return;
    if (event.data?.type !== 'social-oauth-callback') return;

    open.value = false;
    router.reload();
};

onMounted(() => {
    window.addEventListener('message', handleOAuthMessage);
});

onUnmounted(() => {
    window.removeEventListener('message', handleOAuthMessage);
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>{{ $t('accounts.add_social_title') }}</DialogTitle>
                <DialogDescription>
                    {{ $t('accounts.add_social_description') }}
                </DialogDescription>
            </DialogHeader>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <button
                    v-for="platform in platforms"
                    :key="platform.value"
                    class="flex flex-col items-center gap-2 rounded-lg border border-border p-4 text-center transition-colors hover:bg-accent"
                    @click="openOAuthPopup(platform.value)"
                >
                    <img :src="getPlatformLogo(platform.value)" :alt="platform.label" class="h-10 w-10 rounded-full object-contain" />
                    <span class="text-sm font-medium">
                        <template v-if="platform.label.includes('(')">
                            {{ platform.label.split('(')[0].trim() }}
                        </template>
                        <template v-else>{{ platform.label }}</template>
                    </span>
                    <p class="line-clamp-2 text-xs leading-tight text-muted-foreground">
                        {{ getPlatformDescription(platform.value) }}
                    </p>
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
