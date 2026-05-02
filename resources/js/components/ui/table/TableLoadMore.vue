<script setup lang="ts">
import { Spinner } from '@/components/ui/spinner';
</script>

<template>
    <div class="flex items-center justify-center gap-2 border-t py-3 text-sm text-muted-foreground">
        <Spinner />
        <span>{{ $t('common.loading_more') }}</span>
    </div>
</template>
