<script setup lang="ts">
import { computed } from 'vue';

import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';

const props = defineProps<{
    src: string | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const isOpen = computed({
    get: () => props.src !== null,
    set: (val) => { if (!val) emit('close'); },
});
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="max-w-4xl gap-0 border-0 bg-transparent p-0 shadow-none sm:max-w-4xl" :show-close-button="false">
            <DialogTitle class="sr-only">Image preview</DialogTitle>
            <img
                v-if="src"
                :src="src"
                alt="Preview"
                class="max-h-[85vh] w-full cursor-pointer rounded-lg object-contain"
                @click="emit('close')"
            />
        </DialogContent>
    </Dialog>
</template>
