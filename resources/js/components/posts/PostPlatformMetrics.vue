<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { IconChartBar, IconLoader2 } from '@tabler/icons-vue';
import { computed, onMounted, ref } from 'vue';

import { metrics as metricsRoute } from '@/routes/app/posts/platforms';

interface Metric {
    label: string;
    value: number;
}

type MetricsResponse = Metric[] | { unsupported: true; reason: string };

interface Props {
    postId: string;
    postPlatformId: string;
}

const props = defineProps<Props>();

const loading = ref(true);
const metrics = ref<Metric[]>([]);

const hasMetrics = computed(() => metrics.value.length > 0);

const formatNumber = (n: number): string => {
    if (n >= 1_000_000)
        return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (n >= 1_000) return (n / 1_000).toFixed(1).replace(/\.0$/, '') + 'K';
    return n.toString();
};

const http = useHttp<Record<string, never>, MetricsResponse>({});

onMounted(async () => {
    try {
        const response = await http.get(
            metricsRoute.url({
                post: props.postId,
                postPlatform: props.postPlatformId,
            }),
        );

        if (Array.isArray(response)) {
            metrics.value = response;
        }
    } catch {
        // Swallow — empty state hides itself.
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <!-- Loading: subtle inline indicator. -->
    <div
        v-if="loading"
        class="flex items-center gap-2 border-t px-4 py-3 text-xs text-muted-foreground"
    >
        <IconLoader2 class="h-3 w-3 animate-spin" />
        {{ $t('posts.show.metrics_loading') }}
    </div>

    <!-- Loaded with data: full metrics block. -->
    <div v-else-if="hasMetrics" class="border-t px-4 py-3">
        <div
            class="mb-2 flex items-center gap-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
        >
            <IconChartBar class="h-3 w-3" />
            {{ $t('posts.show.metrics') }}
        </div>
        <div class="grid grid-cols-3 gap-2">
            <div
                v-for="metric in metrics"
                :key="metric.label"
                class="rounded-md bg-muted/50 px-2.5 py-1.5"
            >
                <p
                    class="text-[10px] tracking-wider text-muted-foreground uppercase"
                >
                    {{ metric.label }}
                </p>
                <p class="text-sm font-semibold tabular-nums">
                    {{ formatNumber(metric.value) }}
                </p>
            </div>
        </div>
    </div>

    <!-- No data / unsupported: render nothing so the card stays clean. -->
</template>
