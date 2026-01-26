<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { IconChevronLeft, IconChevronRight, IconPlus } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import dayjs from '@/dayjs';
import AppLayout from '@/layouts/AppLayout.vue';
import { calendar } from '@/routes';
import { store as storePost, edit as editPost } from '@/routes/posts';
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
    currentMonth: string;
    view: 'week' | 'month';
}

const props = defineProps<Props>();

const breadcrumbs = computed<BreadcrumbItemType[]>(() => [
    { title: trans('calendar.title'), href: calendar.url() },
]);

// Generate weekday names based on dayjs locale (respects weekStart config)
const weekdayNames = computed(() => {
    const names = [];
    const start = dayjs().startOf('week');
    for (let i = 0; i < 7; i++) {
        names.push(start.add(i, 'day').format('dddd'));
    }
    return names;
});

// Week view computed
const weekStart = computed(() => dayjs(props.currentWeekStart));

const weekDays = computed(() => {
    const days = [];
    for (let i = 0; i < 7; i++) {
        days.push(weekStart.value.add(i, 'day'));
    }
    return days;
});

const weekHeaderTitle = computed(() => {
    const start = weekStart.value;
    const end = weekStart.value.add(6, 'day');

    if (start.month() === end.month()) {
        return `${start.format('MMMM D')} - ${end.format('D, YYYY')}`;
    }
    return `${start.format('MMM D')} - ${end.format('MMM D, YYYY')}`;
});

// Month view computed
const monthDate = computed(() => dayjs(props.currentMonth));

const monthHeaderTitle = computed(() => {
    return monthDate.value.format('MMMM YYYY');
});

const calendarDays = computed(() => {
    const start = monthDate.value.startOf('month').startOf('week');
    const end = monthDate.value.endOf('month').endOf('week');
    const days = [];
    let current = start;

    while (current.isBefore(end) || current.isSame(end, 'day')) {
        days.push(current);
        current = current.add(1, 'day');
    }

    return days;
});

const calendarWeeks = computed(() => {
    const weeks = [];
    for (let i = 0; i < calendarDays.value.length; i += 7) {
        weeks.push(calendarDays.value.slice(i, i + 7));
    }
    return weeks;
});

// Header title based on view
const headerTitle = computed(() => {
    return props.view === 'month' ? monthHeaderTitle.value : weekHeaderTitle.value;
});

const getPostsForDay = (day: dayjs.Dayjs): Post[] => {
    const dateKey = day.format('YYYY-MM-DD');
    return props.posts[dateKey] || [];
};

const navigateWeek = (direction: number) => {
    const newStart = weekStart.value.add(direction * 7, 'day');
    router.get(calendar.url({ query: { view: 'week', week: newStart.format('YYYY-MM-DD') } }), {}, {
        preserveState: true,
    });
};

const navigateMonth = (direction: number) => {
    const newMonth = monthDate.value.add(direction, 'month');
    router.get(calendar.url({ query: { view: 'month', month: newMonth.format('YYYY-MM-DD') } }), {}, {
        preserveState: true,
    });
};

const navigate = (direction: number) => {
    if (props.view === 'month') {
        navigateMonth(direction);
    } else {
        navigateWeek(direction);
    }
};

const goToToday = () => {
    router.get(calendar.url({ query: { view: props.view } }), {}, {
        preserveState: true,
    });
};

const switchView = (view: string) => {
    router.get(calendar.url({ query: { view } }), {}, {
        preserveState: true,
    });
};

const isToday = (day: dayjs.Dayjs): boolean => {
    return day.isSame(dayjs(), 'day');
};

const isCurrentMonth = (day: dayjs.Dayjs): boolean => {
    return day.month() === monthDate.value.month();
};

const getStatusColor = (status: string): string => {
    const colors: Record<string, string> = {
        draft: 'bg-neutral-100 border-neutral-300 text-neutral-700 dark:bg-neutral-800 dark:border-neutral-600 dark:text-neutral-300',
        scheduled: 'bg-blue-50 border-blue-300 text-blue-700 dark:bg-blue-950 dark:border-blue-800 dark:text-blue-300',
        publishing: 'bg-yellow-50 border-yellow-300 text-yellow-700 dark:bg-yellow-950 dark:border-yellow-800 dark:text-yellow-300',
        published: 'bg-green-50 border-green-300 text-green-700 dark:bg-green-950 dark:border-green-800 dark:text-green-300',
        partially_published: 'bg-orange-50 border-orange-300 text-orange-700 dark:bg-orange-950 dark:border-orange-800 dark:text-orange-300',
        failed: 'bg-red-50 border-red-300 text-red-700 dark:bg-red-950 dark:border-red-800 dark:text-red-300',
    };
    return colors[status] || 'bg-neutral-100 border-neutral-300 text-neutral-700 dark:bg-neutral-800 dark:border-neutral-600 dark:text-neutral-300';
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
        'pinterest': '/images/accounts/pinterest.png',
        'bluesky': '/images/accounts/bluesky.png',
        'mastodon': '/images/accounts/mastodon.png',

    };
    return logos[platform];
};

const getPostUrl = (post: Post): string => {
    return editPost.url(post.id);
};

const formatTime = (scheduledAt: string): string => {
    return dayjs.utc(scheduledAt).tz(props.workspace.timezone).format('h:mm A');
};
</script>

<template>

    <Head :title="$t('calendar.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col h-full">
            <!-- Header -->
            <div class="flex items-center justify-between p-4 border-b">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-1">
                        <Button variant="outline" size="icon" @click="navigate(-1)">
                            <IconChevronLeft class="h-4 w-4" />
                        </Button>
                        <Button variant="outline" size="icon" @click="navigate(1)">
                            <IconChevronRight class="h-4 w-4" />
                        </Button>
                    </div>
                    <Button variant="outline" size="sm" @click="goToToday">
                        {{ $t('calendar.today') }}
                    </Button>
                    <h1 class="text-lg font-semibold">
                        {{ headerTitle }}
                    </h1>
                </div>
                <div class="flex items-center gap-4">
                    <Tabs :default-value="view" @update:model-value="switchView">
                        <TabsList>
                            <TabsTrigger value="week">{{ $t('calendar.week') }}</TabsTrigger>
                            <TabsTrigger value="month">{{ $t('calendar.month') }}</TabsTrigger>
                        </TabsList>
                    </Tabs>
                    <Link :href="storePost.url()" method="post">
                        <Button>
                            {{ $t('calendar.new_post') }}
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Week View -->
            <div v-if="view === 'week'" class="flex-1 grid grid-cols-7 divide-x overflow-hidden">
                <div v-for="day in weekDays" :key="day.format('YYYY-MM-DD')" class="flex flex-col min-h-0"
                    :class="{ 'bg-primary/5': isToday(day) }">
                    <!-- Day Header -->
                    <div class="flex flex-col items-center py-3 border-b bg-muted/30">
                        <span class="text-xs font-medium text-muted-foreground">
                            {{ day.format('dddd') }}
                        </span>
                        <span class="mt-1 text-sm font-semibold" :class="{
                            'text-primary': isToday(day),
                            'text-foreground': !isToday(day),
                        }">
                            {{ day.format('D/MMMM') }}
                        </span>
                    </div>

                    <!-- Day Content -->
                    <div class="flex-1 overflow-y-auto p-2 space-y-2">
                        <!-- Add Post Button -->
                        <Link :href="storePost.url({ query: { date: day.format('YYYY-MM-DD') } })" method="post"
                            class="flex items-center justify-center p-2 rounded border border-dashed border-muted-foreground/30 text-muted-foreground hover:border-primary hover:text-primary hover:bg-primary/5 transition-colors">
                            <IconPlus class="h-4 w-4" />
                        </Link>

                        <!-- Posts -->
                        <Link v-for="post in getPostsForDay(day)" :key="post.id" :href="getPostUrl(post)" class="block">
                            <div class="p-2 rounded border text-sm transition-all hover:ring-2 hover:ring-primary hover:ring-offset-1"
                                :class="getStatusColor(post.status)">
                                <!-- Time -->
                                <div class="text-xs font-medium mb-1">
                                    {{ formatTime(post.scheduled_at) }}
                                </div>

                                <!-- Platforms -->
                                <div class="flex -space-x-1 mb-1.5">
                                    <img v-for="pp in post.post_platforms.slice(0, 4)" :key="pp.id"
                                        :src="getPlatformLogo(pp.platform)" :alt="pp.platform"
                                        class="h-5 w-5 rounded-full ring-2 ring-background" />
                                    <span v-if="post.post_platforms.length > 4"
                                        class="flex items-center justify-center h-5 w-5 rounded-full bg-muted text-[10px] font-medium ring-2 ring-background">
                                        +{{ post.post_platforms.length - 4 }}
                                    </span>
                                </div>

                                <!-- Content Preview -->
                                <p class="text-xs line-clamp-2 opacity-80">
                                    {{ post.post_platforms[0]?.content || $t('calendar.no_content') }}
                                </p>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Month View -->
            <div v-else class="flex-1 flex flex-col overflow-hidden">
                <!-- Weekday Headers -->
                <div class="grid grid-cols-7 divide-x border-b bg-muted/30">
                    <div v-for="day in weekdayNames" :key="day"
                        class="py-3 text-center text-xs font-medium text-muted-foreground">
                        {{ day }}
                    </div>
                </div>

                <!-- Calendar Grid -->
                <div class="flex-1 grid divide-y"
                    :style="{ gridTemplateRows: `repeat(${calendarWeeks.length}, minmax(0, 1fr))` }">
                    <div v-for="(week, weekIndex) in calendarWeeks" :key="weekIndex"
                        class="grid grid-cols-7 divide-x min-h-0">
                        <div v-for="day in week" :key="day.format('YYYY-MM-DD')"
                            class="flex flex-col p-2 min-h-0 overflow-hidden group" :class="{
                                'bg-primary/5': isToday(day),
                                'bg-muted/30': !isCurrentMonth(day),
                            }">
                            <!-- Day Header -->
                            <div class="flex items-center justify-between mb-2">
                                <span class="flex items-center justify-center w-7 h-7 text-sm font-medium rounded-full"
                                    :class="{
                                        'bg-primary text-primary-foreground': isToday(day),
                                        'text-muted-foreground': !isCurrentMonth(day),
                                        'text-foreground': isCurrentMonth(day) && !isToday(day),
                                    }">
                                    {{ day.format('D') }}
                                </span>
                                <Link :href="storePost.url({ query: { date: day.format('YYYY-MM-DD') } })" method="post"
                                    class="opacity-0 group-hover:opacity-100 focus:opacity-100 p-1 rounded text-muted-foreground hover:text-primary hover:bg-primary/10 transition-all">
                                    <IconPlus class="h-4 w-4" />
                                </Link>
                            </div>

                            <!-- Posts -->
                            <div class="flex-1 overflow-y-auto space-y-1 min-h-0">
                                <Link v-for="post in getPostsForDay(day).slice(0, 3)" :key="post.id"
                                    :href="getPostUrl(post)" class="block">
                                    <div class="flex items-center justify-between gap-1.5 px-2 py-1 rounded border text-xs transition-colors hover:brightness-95"
                                        :class="getStatusColor(post.status)">
                                        <span class="font-medium shrink-0">{{ formatTime(post.scheduled_at) }}</span>
                                        <div class="flex -space-x-1 shrink-0">
                                            <img v-for="pp in post.post_platforms.slice(0, post.post_platforms.length > 4 ? 3 : 4)"
                                                :key="pp.id" :src="getPlatformLogo(pp.platform)" :alt="pp.platform"
                                                class="h-4 w-4 rounded-full ring-1 ring-background" />
                                            <span v-if="post.post_platforms.length > 4"
                                                class="flex items-center justify-center h-4 w-4 rounded-full bg-muted text-[9px] font-medium ring-1 ring-background">
                                                +{{ post.post_platforms.length - 3 }}
                                            </span>
                                        </div>
                                    </div>
                                </Link>
                                <div v-if="getPostsForDay(day).length > 3"
                                    class="text-xs text-muted-foreground px-2 py-0.5">
                                    {{ $t('calendar.more', { count: getPostsForDay(day).length - 3 }) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>