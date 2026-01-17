<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ChevronLeft, ChevronRight, Plus } from 'lucide-vue-next';
import dayjs from '@/dayjs';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { calendar } from '@/routes';
import { create as createPost, edit as editPost, show as showPost } from '@/routes/posts';
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

interface Workspace {
    id: string;
    name: string;
    timezone: string;
}

interface Props {
    workspace: Workspace;
    posts: Record<string, Post[]>;
    currentWeekStart: string;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Calendar', href: calendar.url() },
];

const weekStart = computed(() => dayjs(props.currentWeekStart));

const weekDays = computed(() => {
    const days = [];
    for (let i = 0; i < 7; i++) {
        days.push(weekStart.value.add(i, 'day'));
    }
    return days;
});

const headerTitle = computed(() => {
    const start = weekStart.value;
    const end = weekStart.value.add(6, 'day');

    if (start.month() === end.month()) {
        return `${start.format('MMMM D')} - ${end.format('D, YYYY')}`;
    }
    return `${start.format('MMM D')} - ${end.format('MMM D, YYYY')}`;
});

const getPostsForDay = (day: dayjs.Dayjs): Post[] => {
    const dateKey = day.format('YYYY-MM-DD');
    return props.posts[dateKey] || [];
};

const navigateWeek = (direction: number) => {
    const newStart = weekStart.value.add(direction * 7, 'day');
    router.get(calendar.url({ query: { week: newStart.format('YYYY-MM-DD') } }), {}, {
        preserveState: true,
    });
};

const goToToday = () => {
    router.get(calendar.url(), {}, {
        preserveState: true,
    });
};

const isToday = (day: dayjs.Dayjs): boolean => {
    return day.isSame(dayjs(), 'day');
};

const getStatusColor = (status: string): string => {
    const colors: Record<string, string> = {
        draft: 'bg-gray-100 border-gray-300 text-gray-700',
        scheduled: 'bg-blue-50 border-blue-300 text-blue-700',
        publishing: 'bg-yellow-50 border-yellow-300 text-yellow-700',
        published: 'bg-green-50 border-green-300 text-green-700',
        partially_published: 'bg-orange-50 border-orange-300 text-orange-700',
        failed: 'bg-red-50 border-red-300 text-red-700',
    };
    return colors[status] || 'bg-gray-100 border-gray-300 text-gray-700';
};

const getPlatformLogo = (platform: string): string => {
    const logos: Record<string, string> = {
        'linkedin': '/images/accounts/linkedin.png',
        'linkedin-page': '/images/accounts/linkedin.png',
        'x': '/images/accounts/x.png',
        'tiktok': '/images/accounts/tiktok.png',
        'youtube': '/images/accounts/youtube.png',
        'facebook': '/images/accounts/facebook.png',
        'instagram': '/images/accounts/instagram.png',
        'threads': '/images/accounts/threads.png',
    };
    return logos[platform] || '/images/accounts/default.png';
};

const getPostUrl = (post: Post): string => {
    return post.status === 'draft' || post.status === 'scheduled'
        ? editPost.url(post.id)
        : showPost.url(post.id);
};

const formatTime = (scheduledAt: string): string => {
    return dayjs.utc(scheduledAt).tz(props.workspace.timezone).format('h:mm A');
};
</script>

<template>
    <Head title="Calendar" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col h-full">
            <!-- Header -->
            <div class="flex items-center justify-between p-4 border-b">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-1">
                        <Button variant="outline" size="icon" @click="navigateWeek(-1)">
                            <ChevronLeft class="h-4 w-4" />
                        </Button>
                        <Button variant="outline" size="icon" @click="navigateWeek(1)">
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                    </div>
                    <Button variant="outline" size="sm" @click="goToToday">
                        Today
                    </Button>
                    <h1 class="text-lg font-semibold">
                        {{ headerTitle }}
                    </h1>
                </div>
                <Link :href="createPost.url()">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        New Post
                    </Button>
                </Link>
            </div>

            <!-- Week Grid -->
            <div class="flex-1 grid grid-cols-7 divide-x overflow-hidden">
                <div
                    v-for="day in weekDays"
                    :key="day.format('YYYY-MM-DD')"
                    class="flex flex-col min-h-0"
                    :class="{ 'bg-primary/5': isToday(day) }"
                >
                    <!-- Day Header -->
                    <div class="flex flex-col items-center py-3 border-b bg-muted/30">
                        <span class="text-xs font-medium text-muted-foreground uppercase">
                            {{ day.format('ddd') }}
                        </span>
                        <span
                            class="mt-1 flex items-center justify-center w-8 h-8 text-sm font-semibold rounded-full"
                            :class="{
                                'bg-primary text-primary-foreground': isToday(day),
                                'text-foreground': !isToday(day),
                            }"
                        >
                            {{ day.format('D') }}
                        </span>
                    </div>

                    <!-- Day Content -->
                    <div class="flex-1 overflow-y-auto p-2 space-y-2">
                        <!-- Add Post Button -->
                        <Link
                            :href="createPost.url({ query: { date: day.format('YYYY-MM-DD') } })"
                            class="flex items-center justify-center p-2 rounded border border-dashed border-muted-foreground/30 text-muted-foreground hover:border-primary hover:text-primary hover:bg-primary/5 transition-colors"
                        >
                            <Plus class="h-4 w-4" />
                        </Link>

                        <!-- Posts -->
                        <Link
                            v-for="post in getPostsForDay(day)"
                            :key="post.id"
                            :href="getPostUrl(post)"
                            class="block"
                        >
                            <div
                                class="p-2 rounded border text-sm transition-all hover:ring-2 hover:ring-primary hover:ring-offset-1"
                                :class="getStatusColor(post.status)"
                            >
                                <!-- Time -->
                                <div class="text-xs font-medium mb-1">
                                    {{ formatTime(post.scheduled_at) }}
                                </div>

                                <!-- Platforms -->
                                <div class="flex -space-x-1 mb-1.5">
                                    <img
                                        v-for="pp in post.post_platforms.slice(0, 4)"
                                        :key="pp.id"
                                        :src="getPlatformLogo(pp.platform)"
                                        :alt="pp.platform"
                                        class="h-5 w-5 rounded-full ring-2 ring-white"
                                    />
                                    <span
                                        v-if="post.post_platforms.length > 4"
                                        class="flex items-center justify-center h-5 w-5 rounded-full bg-muted text-[10px] font-medium ring-2 ring-white"
                                    >
                                        +{{ post.post_platforms.length - 4 }}
                                    </span>
                                </div>

                                <!-- Content Preview -->
                                <p class="text-xs line-clamp-2 opacity-80">
                                    {{ post.post_platforms[0]?.content || 'No content' }}
                                </p>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
