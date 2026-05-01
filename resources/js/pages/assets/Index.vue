<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import GalleryBrowser from '@/components/assets/GalleryBrowser.vue';
import AppLayout from '@/layouts/AppLayout.vue';
</script>

<template>
    <Head :title="$t('assets.title')" />

    <AppLayout :title="$t('assets.title')">
        <div class="flex flex-col gap-6 p-6">
            <GalleryBrowser mode="standalone" />
        </div>
    </AppLayout>
</template>
