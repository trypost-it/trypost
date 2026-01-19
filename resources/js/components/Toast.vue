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
const animate = ref(false);
const style = computed(() => usePage().props.flash?.bannerStyle || 'success');
const message = computed(() => usePage().props.flash?.banner || '');

let timeoutId: ReturnType<typeof setTimeout> | null = null;

watch(
    message,
    (newMessage) => {
        if (newMessage) {
            show.value = true;
            // Reseta a animação
            animate.value = false;

            // Inicia a animação logo após mostrar
            setTimeout(() => {
                animate.value = true;
            }, 10);

            // Limpa timeout anterior se existir
            if (timeoutId) {
                clearTimeout(timeoutId);
            }

            // Define novo timeout
            timeoutId = setTimeout(() => {
                show.value = false;
                animate.value = false;
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
        class="pointer-events-none fixed inset-0 z-50 flex px-4 py-6 sm:items-start sm:p-6"
    >
        <div
            class="absolute right-5 bottom-5 flex w-full flex-col items-end space-y-4"
        >
            <transition
                enter-active-class="transform ease-out duration-300 transition"
                enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
                enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="show"
                    class="ring-opacity-5 pointer-events-auto relative w-full max-w-sm overflow-hidden rounded-lg bg-zinc-800 shadow-2xl ring-1 ring-transparent dark:ring-zinc-700"
                >
                    <!-- Barra de progresso animada -->
                    <div
                        class="absolute top-0 left-0 h-full transition-all duration-[3000ms] ease-linear"
                        :class="{
                            'w-0': !animate,
                            'w-full': animate,
                            'bg-green-500/10': style === 'success',
                            'bg-red-500/10': style === 'danger',
                            'bg-blue-500/10': style === 'info',
                            'bg-yellow-500/10': style === 'warning',
                        }"
                    />
                    <div class="relative p-4">
                        <div class="flex items-start">
                            <div class="mt-0.5 flex-shrink-0">
                                <IconCircleCheck
                                    v-if="style == 'success'"
                                    class="h-6 w-6 stroke-2 text-green-400"
                                    aria-hidden="true"
                                />
                                <IconCircleX
                                    v-if="style == 'danger'"
                                    class="h-6 w-6 stroke-2 text-red-400"
                                    aria-hidden="true"
                                />
                                <IconInfoCircle
                                    v-if="style == 'info'"
                                    class="h-6 w-6 stroke-2 text-blue-400"
                                    aria-hidden="true"
                                />
                                <IconAlertTriangle
                                    v-if="style == 'warning'"
                                    class="h-6 w-6 stroke-2 text-yellow-400"
                                    aria-hidden="true"
                                />
                            </div>
                            <div class="ml-3 w-0 flex-1 pt-0.5">
                                <p class="text-sm font-medium text-white">
                                    {{ message }}
                                </p>
                            </div>
                            <div class="ml-4 flex flex-shrink-0">
                                <button
                                    type="button"
                                    @click="show = false"
                                    class="inline-flex rounded-md bg-transparent text-zinc-400 hover:text-zinc-500 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none"
                                >
                                    <span class="sr-only">Close</span>
                                    <IconX class="h-5 w-5" aria-hidden="true" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </div>
</template>
