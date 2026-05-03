<script setup lang="ts">
import {
    IconCalendar,
    IconClock,
    IconHash,
    IconPhoto,
    IconUsers,
    IconVideo,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { onBeforeUnmount, onMounted, ref, computed } from 'vue';

withDefaults(defineProps<{
    title?: string;
    description?: string;
    showLegal?: boolean;
}>(), {
    showLegal: false,
});

const slideKeys = ['calendar', 'scheduling', 'media', 'video', 'team', 'signatures'] as const;

const slideIcons = {
    calendar: IconCalendar,
    scheduling: IconClock,
    media: IconPhoto,
    video: IconVideo,
    team: IconUsers,
    signatures: IconHash,
};

const slides = computed(() =>
    slideKeys.map((key) => ({
        icon: slideIcons[key],
        title: trans(`auth.slides.${key}.title`),
        description: trans(`auth.slides.${key}.description`),
    })),
);

const activeIndex = ref(0);
const isPaused = ref(false);
let intervalId: ReturnType<typeof setInterval> | null = null;

const activeSlide = computed(() => slides.value[activeIndex.value]);

const goTo = (index: number) => {
    activeIndex.value = index;
    restartInterval();
};

const startInterval = () => {
    intervalId = setInterval(() => {
        if (!isPaused.value) {
            activeIndex.value = (activeIndex.value + 1) % slides.value.length;
        }
    }, 4000);
};

const restartInterval = () => {
    if (intervalId) {
        clearInterval(intervalId);
    }
    startInterval();
};

onMounted(() => {
    startInterval();
});

onBeforeUnmount(() => {
    if (intervalId) {
        clearInterval(intervalId);
    }
});

const platforms = [
    { name: 'LinkedIn', icon: '/images/accounts/linkedin.png' },
    { name: 'X', icon: '/images/accounts/x.png' },
    { name: 'Instagram', icon: '/images/accounts/instagram.png' },
    { name: 'Facebook', icon: '/images/accounts/facebook.png' },
    { name: 'TikTok', icon: '/images/accounts/tiktok.png' },
    { name: 'YouTube', icon: '/images/accounts/youtube.png' },
    { name: 'Threads', icon: '/images/accounts/threads.png' },
    { name: 'Pinterest', icon: '/images/accounts/pinterest.png' },
    { name: 'Bluesky', icon: '/images/accounts/bluesky.png' },
    { name: 'Mastodon', icon: '/images/accounts/mastodon.png' },
];
</script>

<template>
    <div class="grid min-h-svh lg:grid-cols-2">
        <div class="flex flex-col gap-4 p-6 md:p-10">
            <div class="flex items-start">
                <img
                    src="/images/trypost/logo-light.png"
                    alt="TryPost"
                    class="h-7 dark:hidden"
                />
                <img
                    src="/images/trypost/logo-dark.png"
                    alt="TryPost"
                    class="hidden h-7 dark:block"
                />
            </div>

            <div class="flex flex-1 items-center justify-center">
                <div class="w-full max-w-md">
                    <div class="flex flex-col gap-6">
                        <div class="flex flex-col items-center gap-2 text-center">
                            <h1 v-if="title" class="text-2xl font-bold">{{ title }}</h1>
                            <p v-if="description" class="text-sm text-balance text-muted-foreground">
                                {{ description }}
                            </p>
                        </div>

                        <slot />
                    </div>
                </div>
            </div>

            <!-- eslint-disable-next-line vue/no-v-html -->
            <div
                v-if="showLegal"
                class="text-center text-xs text-muted-foreground [&_a]:underline [&_a]:underline-offset-4 [&_a]:hover:text-primary"
                v-html="trans('auth.legal')"
            />
        </div>

        <div
            class="relative hidden overflow-hidden lg:block"
            @mouseenter="isPaused = true"
            @mouseleave="isPaused = false"
        >
            <div class="absolute inset-0 bg-gradient-to-br from-zinc-900 via-zinc-950 to-zinc-950" />

            <div
                class="absolute inset-0 opacity-[0.015]"
                style="background-image: url(&quot;data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 0h40v40H0z' fill='none'/%3E%3Cpath d='M0 0h1v40H0zM40 0v1H0V0z' fill='%23fff'/%3E%3C/svg%3E&quot;)"
            />

            <div class="relative flex h-full flex-col items-center justify-center px-12 xl:px-16">
                <!-- Platform logos -->
                <div class="relative h-[280px] w-full max-w-md">
                    <template v-for="(slide, index) in slides" :key="index">
                        <Transition
                            enter-active-class="transition-all duration-500 ease-out"
                            leave-active-class="transition-all duration-500 ease-out"
                            enter-from-class="opacity-0 translate-y-4"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-from-class="opacity-100 translate-y-0"
                            leave-to-class="opacity-0 -translate-y-4"
                        >
                            <div
                                v-if="activeIndex === index"
                                class="absolute inset-0 flex items-center justify-center"
                            >
                                <div class="w-full rounded-xl border border-white/10 bg-white/[0.04] p-6 shadow-2xl backdrop-blur-sm">
                                    <div class="mb-5 flex items-center gap-2">
                                        <div class="size-2.5 rounded-full bg-red-400/80" />
                                        <div class="size-2.5 rounded-full bg-yellow-400/80" />
                                        <div class="size-2.5 rounded-full bg-green-400/80" />
                                        <div class="ml-3 h-5 w-48 rounded-md bg-white/5" />
                                    </div>

                                    <div class="flex items-center justify-center py-8">
                                        <div class="flex size-20 items-center justify-center rounded-2xl border border-white/10 bg-white/[0.06]">
                                            <component :is="slide.icon" class="size-10 text-white/80" />
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap justify-center gap-2 pt-2">
                                        <img
                                            v-for="platform in platforms"
                                            :key="platform.name"
                                            :src="platform.icon"
                                            :alt="platform.name"
                                            class="size-7 rounded-full border border-white/10 bg-white/5 p-0.5 opacity-60"
                                        />
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </template>
                </div>

                <!-- Text content -->
                <div class="w-full max-w-md text-center">
                    <div class="relative h-[88px]">
                        <TransitionGroup
                            enter-active-class="transition-all duration-400 ease-out"
                            leave-active-class="transition-all duration-300 ease-in"
                            enter-from-class="opacity-0 translate-y-2"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                        >
                            <div :key="activeIndex" class="absolute inset-x-0 top-0">
                                <h3 class="text-xl font-semibold tracking-tight text-white">
                                    {{ activeSlide.title }}
                                </h3>
                                <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-zinc-400">
                                    {{ activeSlide.description }}
                                </p>
                            </div>
                        </TransitionGroup>
                    </div>

                    <!-- Dots -->
                    <div class="flex items-center justify-center gap-2">
                        <button
                            v-for="(_, index) in slides"
                            :key="index"
                            class="group relative flex h-5 cursor-pointer items-center justify-center"
                            @click="goTo(index)"
                        >
                            <span
                                class="block h-1.5 rounded-full transition-all duration-300"
                                :class="activeIndex === index
                                    ? 'w-6 bg-white'
                                    : 'w-1.5 bg-zinc-600 group-hover:bg-zinc-500'
                                "
                            />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
