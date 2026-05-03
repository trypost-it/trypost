<script setup lang="ts">
import { ref } from 'vue';

import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';

interface Signature {
    id: string;
    name: string;
    content: string;
}

defineProps<{
    signatures: Signature[];
}>();

const emit = defineEmits<{
    (e: 'select', signature: Signature): void;
}>();

const isOpen = ref(false);

const handleSelect = (signature: Signature) => {
    emit('select', signature);
    isOpen.value = false;
};

const open = () => {
    isOpen.value = true;
};

const close = () => {
    isOpen.value = false;
};

defineExpose({
    open,
    close,
});
</script>

<template>
    <CommandDialog v-model:open="isOpen">
        <CommandInput :placeholder="$t('posts.edit.signatures_modal.search')" />
        <CommandList>
            <CommandEmpty>
                {{ $t('posts.edit.signatures_modal.no_results') }}
            </CommandEmpty>
            <CommandGroup>
                <CommandItem v-for="signature in signatures" :key="signature.id" :value="signature.name"
                    class="flex flex-col items-start gap-1 py-3" @select="handleSelect(signature)">
                    <div class="font-medium text-sm">
                        {{ signature.name }}
                    </div>
                    <p class="text-xs  line-clamp-2 w-full">
                        {{ signature.content }}
                    </p>
                </CommandItem>
            </CommandGroup>
        </CommandList>
    </CommandDialog>
</template>
