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

interface Hashtag {
    id: string;
    name: string;
    hashtags: string;
}

defineProps<{
    hashtags: Hashtag[];
}>();

const emit = defineEmits<{
    (e: 'select', hashtag: Hashtag): void;
}>();

const isOpen = ref(false);

const handleSelect = (hashtag: Hashtag) => {
    emit('select', hashtag);
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
        <CommandInput :placeholder="$t('posts.edit.hashtags_modal.search')" />
        <CommandList>
            <CommandEmpty>
                {{ $t('posts.edit.hashtags_modal.no_results') }}
            </CommandEmpty>
            <CommandGroup>
                <CommandItem v-for="hashtag in hashtags" :key="hashtag.id" :value="hashtag.name"
                    class="flex flex-col items-start gap-1 py-3" @select="handleSelect(hashtag)">
                    <div class="font-medium text-sm">
                        {{ hashtag.name }}
                    </div>
                    <p class="text-xs  line-clamp-2 w-full">
                        {{ hashtag.hashtags }}
                    </p>
                </CommandItem>
            </CommandGroup>
        </CommandList>
    </CommandDialog>
</template>