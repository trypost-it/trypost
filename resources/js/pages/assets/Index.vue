<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import GalleryBrowser from '@/components/assets/GalleryBrowser.vue';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs = computed<BreadcrumbItem[]>(() => [{ title: trans('assets.title') }]);
</script>

<template>
    <Head :title="$t('assets.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <PageHeader :title="$t('assets.title')" />
            <GalleryBrowser mode="standalone" />
        </div>
    </AppLayout>
</template>
