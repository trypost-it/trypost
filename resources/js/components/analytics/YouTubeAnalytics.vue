<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';

import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import dayjs from '@/dayjs';
import { formatNumber } from '@/lib/utils';
import { show as showAnalytics } from '@/routes/app/analytics';

interface MetricItem {
    label: string;
    value: number;
}

const props = defineProps<{
    accountId: string;
    dateRange: { start: Date; end: Date };
}>();

const metrics = ref<MetricItem[]>([]);
const isLoading = ref(false);

const http = useHttp<Record<string, never>, { metrics: MetricItem[] }>({});

const fetchMetrics = async () => {
    isLoading.value = true;
    metrics.value = [];

    try {
        const response = await http.get(showAnalytics.url(props.accountId, {
            query: {
                since: dayjs(props.dateRange.start).format('YYYY-MM-DD'),
                until: dayjs(props.dateRange.end).format('YYYY-MM-DD'),
            },
        }));
        metrics.value = response?.metrics || [];
    } catch {
        metrics.value = [];
    } finally {
        isLoading.value = false;
    }
};

watch(() => props.accountId, () => {
    fetchMetrics();
});

watch(() => props.dateRange, () => {
    fetchMetrics();
}, { deep: true });

onMounted(() => {
    fetchMetrics();
});

defineExpose({ supportsDateRange: true });
</script>

<template>
    <!-- Loading -->
    <div v-if="isLoading" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Card v-for="i in 7" :key="i">
            <CardContent class="p-6">
                <Skeleton class="mb-3 h-4 w-24" />
                <Skeleton class="h-8 w-32" />
            </CardContent>
        </Card>
    </div>

    <!-- Metrics -->
    <div v-else-if="metrics.length > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Card v-for="metric in metrics" :key="metric.label">
            <CardContent class="p-6">
                <p class="text-sm text-muted-foreground">{{ metric.label }}</p>
                <p class="mt-2 text-3xl font-bold tracking-tight">
                    {{ formatNumber(metric.value) }}
                </p>
            </CardContent>
        </Card>
    </div>

    <!-- No Data -->
    <div v-else class="flex h-full items-center justify-center text-muted-foreground">
        {{ $t('analytics.no_data') }}
    </div>
</template>
