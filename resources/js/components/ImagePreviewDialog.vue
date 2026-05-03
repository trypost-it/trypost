<script setup lang="ts">
import { IconChevronLeft, IconChevronRight } from '@tabler/icons-vue';
import { computed, onUnmounted, watch } from 'vue';

import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';

interface Props {
    /** Single-image mode (backward compatible). */
    src?: string | null;
    /** Multi-image mode — pass the full list and bind v-model:index to control which one is shown. */
    images?: string[];
}

const props = withDefaults(defineProps<Props>(), {
    src: null,
    images: () => [],
});

const index = defineModel<number | null>('index', { default: null });

const emit = defineEmits<{
    close: [];
}>();

// Multi-image takes precedence; falls back to single src when no list provided.
const allImages = computed<string[]>(() =>
    props.images.length > 0 ? props.images : (props.src ? [props.src] : []),
);

// In multi-image mode the dialog is open when index is a number. In single src
// mode (legacy) it's open whenever src is set.
const isOpen = computed({
    get: () => allImages.value.length > 0 && (props.images.length === 0 || index.value !== null),
    set: (val) => {
        if (!val) emit('close');
    },
});

const safeIndex = computed(() =>
    Math.max(0, Math.min(index.value ?? 0, allImages.value.length - 1)),
);
const currentImage = computed(() => allImages.value[safeIndex.value] ?? null);
const hasPrev = computed(() => safeIndex.value > 0);
const hasNext = computed(() => safeIndex.value < allImages.value.length - 1);
const showNav = computed(() => allImages.value.length > 1);

const goPrev = () => {
    if (hasPrev.value) index.value = safeIndex.value - 1;
};
const goNext = () => {
    if (hasNext.value) index.value = safeIndex.value + 1;
};

const onKeydown = (e: KeyboardEvent) => {
    if (!isOpen.value) return;
    if (e.key === 'ArrowLeft') {
        e.preventDefault();
        goPrev();
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        goNext();
    }
};

watch(
    isOpen,
    (open) => {
        if (open) {
            window.addEventListener('keydown', onKeydown);
        } else {
            window.removeEventListener('keydown', onKeydown);
        }
    },
    { immediate: true },
);

onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent
            class="max-w-5xl gap-0 border-0 bg-transparent p-0 shadow-none outline-none focus:outline-none focus-visible:outline-none sm:max-w-5xl"
            :show-close-button="false"
        >
            <DialogTitle class="sr-only">Image preview</DialogTitle>
            <div class="relative flex justify-center">
                <img
                    v-if="currentImage"
                    :src="currentImage"
                    alt="Preview"
                    class="max-h-[85vh] max-w-full cursor-pointer rounded-2xl object-contain"
                    @click="emit('close')"
                />

                <button
                    v-if="showNav && hasPrev"
                    type="button"
                    aria-label="Previous image"
                    class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white transition hover:bg-black/70"
                    @click.stop="goPrev"
                >
                    <IconChevronLeft class="size-6" />
                </button>

                <button
                    v-if="showNav && hasNext"
                    type="button"
                    aria-label="Next image"
                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white transition hover:bg-black/70"
                    @click.stop="goNext"
                >
                    <IconChevronRight class="size-6" />
                </button>

                <div
                    v-if="showNav"
                    class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-black/60 px-3 py-1 text-xs text-white tabular-nums"
                >
                    {{ safeIndex + 1 }} / {{ allImages.length }}
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
