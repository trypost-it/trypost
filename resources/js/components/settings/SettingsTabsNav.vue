<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Tab {
    label: string;
    href: string;
    name: string;
}

defineProps<{
    tabs: Tab[];
    active: string;
}>();
</script>

<template>
    <div
        class="bg-muted text-muted-foreground inline-flex h-9 w-fit items-center justify-center rounded-lg p-[3px]"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.name"
            :href="tab.href"
            :class="[
                'inline-flex h-[calc(100%-1px)] items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-transparent px-3 py-1 text-sm font-medium transition-[color,box-shadow]',
                active === tab.name
                    ? 'bg-background text-foreground shadow-sm dark:border-input dark:bg-input/30 dark:text-foreground'
                    : 'hover:text-foreground',
            ]"
        >
            {{ tab.label }}
        </Link>
    </div>
</template>
