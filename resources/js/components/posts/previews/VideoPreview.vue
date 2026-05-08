<script setup lang="ts">
import { IconPlayerPlayFilled } from '@tabler/icons-vue';
import { ref } from 'vue';

const props = withDefaults(
    defineProps<{
        src: string;
        videoClass?: string;
    }>(),
    {
        videoClass: 'w-full h-full object-cover',
    },
);

const videoRef = ref<HTMLVideoElement | null>(null);
const isPlaying = ref(false);

const toggle = () => {
    const el = videoRef.value;
    if (!el) return;
    if (el.paused) {
        void el.play();
    } else {
        el.pause();
    }
};
</script>

<template>
    <div class="relative h-full w-full" @click="toggle">
        <video
            ref="videoRef"
            :src="props.src"
            :class="props.videoClass"
            playsinline
            preload="metadata"
            @play="isPlaying = true"
            @pause="isPlaying = false"
            @ended="isPlaying = false"
        />
        <button
            v-show="!isPlaying"
            type="button"
            class="absolute inset-0 flex cursor-pointer items-center justify-center bg-black/10 transition-colors hover:bg-black/20"
            aria-label="Play"
        >
            <span
                class="flex size-14 items-center justify-center rounded-full bg-black/55 ring-1 ring-white/30 backdrop-blur-sm transition-transform hover:scale-110"
            >
                <IconPlayerPlayFilled class="size-7 text-white drop-shadow" />
            </span>
        </button>
    </div>
</template>
