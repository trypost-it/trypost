<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
    total?: number;
}>();
</script>

<template>
    <div>
        <h2 class="text-xl font-semibold tracking-tight">
            {{ title }}
            <span v-if="total != null && total > 0" class="text-muted-foreground">({{ total }})</span>
        </h2>
        <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
    </div>
</template>
