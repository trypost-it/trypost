<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ChevronLeft, ChevronRight, Plus } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { type BreadcrumbItemType } from '@/types';

interface PostPlatform {
    id: string;
    platform: string;
    content: string;
    status: string;
    social_account: {
        id: string;
        platform: string;
        display_name: string;
    };
}

interface Post {
    id: string;
    status: string;
    scheduled_at: string;
    post_platforms: PostPlatform[];
}

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
}

interface Workspace {
    id: string;
    name: string;
}

interface Props {
    workspace: Workspace;
    posts: Record<string, Post[]>;
    socialAccounts: SocialAccount[];
    currentMonth: number;
    currentYear: number;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    {
        title: 'Workspaces',
        href: '/workspaces',
    },
    {
        title: props.workspace.name,
        href: `/workspaces/${props.workspace.id}`,
    },
    {
        title: 'Calendário',
        href: `/workspaces/${props.workspace.id}/calendar`,
    },
];

const monthNames = [
    'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
];

const dayNames = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

const currentDate = computed(() => new Date(props.currentYear, props.currentMonth - 1, 1));

const daysInMonth = computed(() => {
    return new Date(props.currentYear, props.currentMonth, 0).getDate();
});

const firstDayOfMonth = computed(() => {
    return new Date(props.currentYear, props.currentMonth - 1, 1).getDay();
});

const calendarDays = computed(() => {
    const days: (number | null)[] = [];

    for (let i = 0; i < firstDayOfMonth.value; i++) {
        days.push(null);
    }

    for (let i = 1; i <= daysInMonth.value; i++) {
        days.push(i);
    }

    return days;
});

const getPostsForDay = (day: number): Post[] => {
    const dateKey = `${props.currentYear}-${String(props.currentMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    return props.posts[dateKey] || [];
};

const navigateMonth = (direction: number) => {
    let newMonth = props.currentMonth + direction;
    let newYear = props.currentYear;

    if (newMonth > 12) {
        newMonth = 1;
        newYear++;
    } else if (newMonth < 1) {
        newMonth = 12;
        newYear--;
    }

    router.get(`/workspaces/${props.workspace.id}/calendar`, {
        month: newMonth,
        year: newYear,
    }, {
        preserveState: true,
    });
};

const getStatusColor = (status: string): string => {
    const colors: Record<string, string> = {
        draft: 'bg-gray-400',
        scheduled: 'bg-blue-500',
        publishing: 'bg-yellow-500',
        published: 'bg-green-500',
        failed: 'bg-red-500',
    };
    return colors[status] || 'bg-gray-400';
};

const getPlatformEmoji = (platform: string): string => {
    const emojis: Record<string, string> = {
        linkedin: '💼',
        twitter: '𝕏',
        tiktok: '🎵',
    };
    return emojis[platform] || '🌐';
};

const isToday = (day: number): boolean => {
    const today = new Date();
    return (
        day === today.getDate() &&
        props.currentMonth === today.getMonth() + 1 &&
        props.currentYear === today.getFullYear()
    );
};
</script>

<template>
    <Head title="Calendário" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Calendário</h1>
                    <p class="text-muted-foreground">
                        Visualize e agende seus posts
                    </p>
                </div>
                <Link :href="`/workspaces/${workspace.id}/posts/create`">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        Novo Post
                    </Button>
                </Link>
            </div>

            <Card>
                <CardContent class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <Button variant="outline" size="icon" @click="navigateMonth(-1)">
                            <ChevronLeft class="h-4 w-4" />
                        </Button>
                        <h2 class="text-lg font-semibold">
                            {{ monthNames[currentMonth - 1] }} {{ currentYear }}
                        </h2>
                        <Button variant="outline" size="icon" @click="navigateMonth(1)">
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                    </div>

                    <div class="grid grid-cols-7 gap-px bg-muted rounded-lg overflow-hidden">
                        <div
                            v-for="dayName in dayNames"
                            :key="dayName"
                            class="bg-background p-2 text-center text-sm font-medium text-muted-foreground"
                        >
                            {{ dayName }}
                        </div>

                        <div
                            v-for="(day, index) in calendarDays"
                            :key="index"
                            class="bg-background min-h-[100px] p-1"
                            :class="{ 'opacity-50': !day }"
                        >
                            <div v-if="day" class="h-full">
                                <div class="flex items-center justify-between mb-1">
                                    <span
                                        class="text-sm font-medium"
                                        :class="{
                                            'bg-primary text-primary-foreground rounded-full w-6 h-6 flex items-center justify-center': isToday(day)
                                        }"
                                    >
                                        {{ day }}
                                    </span>
                                    <Link
                                        :href="`/workspaces/${workspace.id}/posts/create?date=${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`"
                                        class="opacity-0 hover:opacity-100 transition-opacity"
                                    >
                                        <Button variant="ghost" size="icon" class="h-6 w-6">
                                            <Plus class="h-3 w-3" />
                                        </Button>
                                    </Link>
                                </div>

                                <div class="space-y-1">
                                    <Link
                                        v-for="post in getPostsForDay(day)"
                                        :key="post.id"
                                        :href="`/workspaces/${workspace.id}/posts/${post.id}`"
                                        class="block"
                                    >
                                        <div
                                            class="text-xs p-1 rounded truncate hover:bg-accent transition-colors"
                                            :class="getStatusColor(post.status)"
                                        >
                                            <span class="text-white flex items-center gap-1">
                                                <span v-for="pp in post.post_platforms" :key="pp.id">
                                                    {{ getPlatformEmoji(pp.platform) }}
                                                </span>
                                                <span class="truncate">
                                                    {{ post.post_platforms[0]?.content?.substring(0, 20) || 'Sem conteúdo' }}
                                                </span>
                                            </span>
                                        </div>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
