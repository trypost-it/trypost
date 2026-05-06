<script setup lang="ts">
import { SidebarTrigger } from '@/components/ui/sidebar';
</script>

<template>
    <header
        class="flex h-14 shrink-0 items-center justify-between gap-2 border-b-2 border-foreground bg-card px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <slot name="left" />
        </div>
        <div class="flex items-center gap-2">
            <slot name="right" />
        </div>
    </header>
</template>
