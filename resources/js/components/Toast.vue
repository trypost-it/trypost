<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    IconAlertTriangle,
    IconCircleCheck,
    IconCircleX,
    IconInfoCircle,
    IconX,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

const show = ref(false);
const style = computed(() => usePage().props.flash?.bannerStyle || 'success');
const message = computed(() => usePage().props.flash?.banner || '');

let timeoutId: ReturnType<typeof setTimeout> | null = null;

watch(
    message,
    (newMessage) => {
        if (newMessage) {
            show.value = true;

            if (timeoutId) {
                clearTimeout(timeoutId);
            }

            timeoutId = setTimeout(() => {
                show.value = false;
                timeoutId = null;
            }, 3000);
        }
    },
    { immediate: true },
);
</script>

<template>
    <div
        v-if="show && message"
        class="pointer-events-none fixed inset-0 z-50 flex items-end justify-end p-6"
    >
        <div
            class="pointer-events-auto w-full max-w-sm rounded-lg border bg-background p-4 shadow-lg"
        >
            <div class="flex items-start gap-3">
                <IconCircleCheck
                    v-if="style === 'success'"
                    class="size-5 shrink-0 text-green-500"
                />
                <IconCircleX
                    v-if="style === 'danger'"
                    class="size-5 shrink-0 text-destructive"
                />
                <IconInfoCircle
                    v-if="style === 'info'"
                    class="size-5 shrink-0 text-blue-500"
                />
                <IconAlertTriangle
                    v-if="style === 'warning'"
                    class="size-5 shrink-0 text-yellow-500"
                />
                <p class="flex-1 text-sm font-medium text-foreground">
                    {{ message }}
                </p>
                <button
                    type="button"
                    @click="show = false"
                    class="shrink-0 rounded-md text-muted-foreground hover:text-foreground focus:outline-none"
                >
                    <span class="sr-only">Close</span>
                    <IconX class="size-4" />
                </button>
            </div>
        </div>
    </div>
</template>
