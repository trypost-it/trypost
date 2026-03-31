<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { IconArchive, IconBell, IconCheck, IconChecks, IconInbox, IconX } from '@tabler/icons-vue';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import dayjs from '@/dayjs';
import { index, read, readAll, archiveAll } from '@/routes/app/notifications';

interface Notification {
    id: string;
    type: string;
    title: string;
    body: string;
    data: Record<string, string> | null;
    read_at: string | null;
    archived_at: string | null;
    created_at: string;
}

const notifications = ref<Notification[]>([]);
const unreadCount = ref(0);
const loading = ref(false);
const show = ref(false);
const panel = ref<HTMLElement | null>(null);

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const fetchNotifications = async () => {
    loading.value = true;
    try {
        const response = await fetch(index.url(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const data = await response.json();
        notifications.value = data.notifications;
        unreadCount.value = data.unread_count;
    } finally {
        loading.value = false;
    }
};

const handleMarkAsRead = async (notification: Notification) => {
    await fetch(read.url(notification.id), {
        method: 'PUT',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
        credentials: 'same-origin',
    });

    notification.read_at = new Date().toISOString();
    unreadCount.value = Math.max(0, unreadCount.value - 1);
};

const handleMarkAllAsRead = async () => {
    await fetch(readAll.url(), {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
        credentials: 'same-origin',
    });

    notifications.value = notifications.value.map((n) => ({
        ...n,
        read_at: n.read_at ?? new Date().toISOString(),
    }));
    unreadCount.value = 0;
};

const handleArchiveAll = async () => {
    await fetch(archiveAll.url(), {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
        credentials: 'same-origin',
    });

    notifications.value = [];
    unreadCount.value = 0;
};

const handleNotificationClick = (notification: Notification) => {
    if (!notification.read_at) {
        handleMarkAsRead(notification);
    }

    close();

    if (notification.data?.post_id) {
        router.visit(`/posts/${notification.data.post_id}/edit`);
    } else if (notification.data?.social_account_id || notification.data?.workspace_id) {
        router.visit('/accounts');
    }
};

const open = () => {
    show.value = true;
    fetchNotifications();
};

const close = () => {
    show.value = false;
};

const toggle = () => {
    if (show.value) {
        close();
    } else {
        open();
    }
};

const onClickOutside = (event: MouseEvent) => {
    if (panel.value && !panel.value.contains(event.target as Node)) {
        close();
    }
};

const onEscape = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        close();
    }
};

const formatTime = (date: string) => {
    return dayjs.utc(date).fromNow();
};

watch(show, (value) => {
    if (value) {
        setTimeout(() => {
            document.addEventListener('click', onClickOutside);
            document.addEventListener('keydown', onEscape);
        }, 0);
    } else {
        document.removeEventListener('click', onClickOutside);
        document.removeEventListener('keydown', onEscape);
    }
});

onMounted(() => {
    fetchNotifications();
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onClickOutside);
    document.removeEventListener('keydown', onEscape);
});
</script>

<template>
    <Button variant="ghost" size="icon" class="relative size-8 shrink-0" @click.stop="toggle">
        <IconBell class="size-4" />
        <span
            v-if="unreadCount > 0"
            class="absolute -top-0.5 -right-0.5 flex size-4 items-center justify-center rounded-full bg-destructive text-[8px] font-bold text-destructive-foreground"
        >
            {{ unreadCount > 9 ? '9+' : unreadCount }}
        </span>
    </Button>

    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-x-2 opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="-translate-x-2 opacity-0"
        >
            <div
                v-if="show"
                ref="panel"
                class="fixed left-[17rem] bottom-2 z-50 w-[22rem] h-[32rem] flex flex-col rounded-xl border border-border bg-card shadow-lg"
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-4 pt-3 pb-2">
                    <h3 class="text-sm font-semibold">{{ $t('sidebar.notifications') }}</h3>
                    <div class="flex items-center gap-0.5">
                        <Tooltip v-if="notifications.length > 0">
                            <TooltipTrigger as-child>
                                <button
                                    type="button"
                                    class="p-1 text-muted-foreground hover:text-foreground transition-colors rounded"
                                    @click="handleMarkAllAsRead"
                                >
                                    <IconChecks class="size-4" />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>{{ $t('sidebar.mark_all_read') }}</TooltipContent>
                        </Tooltip>
                        <Tooltip v-if="notifications.length > 0">
                            <TooltipTrigger as-child>
                                <button
                                    type="button"
                                    class="p-1 text-muted-foreground hover:text-foreground transition-colors rounded"
                                    @click="handleArchiveAll"
                                >
                                    <IconArchive class="size-4" />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>{{ $t('sidebar.archive_all') }}</TooltipContent>
                        </Tooltip>
                        <button
                            type="button"
                            class="p-1 text-muted-foreground hover:text-foreground transition-colors rounded"
                            @click="close"
                        >
                            <IconX class="size-4" />
                        </button>
                    </div>
                </div>

                <!-- Notification list -->
                <div class="flex-1 overflow-y-auto">
                    <div v-if="notifications.length > 0" class="divide-y divide-border">
                        <div
                            v-for="notification in notifications"
                            :key="notification.id"
                            class="px-3 py-2.5 flex items-start gap-2.5 hover:bg-muted/50 transition-colors cursor-pointer"
                            @click="handleNotificationClick(notification)"
                        >
                            <div class="flex items-center mt-1.5 shrink-0">
                                <div
                                    :class="[
                                        'size-1.5 rounded-full',
                                        !notification.read_at ? 'bg-primary' : 'bg-transparent',
                                    ]"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-medium truncate">{{ notification.title }}</p>
                                <p class="text-xs text-muted-foreground truncate">{{ notification.body }}</p>
                                <p class="text-[11px] text-muted-foreground/70 mt-0.5">{{ formatTime(notification.created_at) }}</p>
                            </div>
                            <div class="shrink-0" @click.stop>
                                <Tooltip v-if="!notification.read_at">
                                    <TooltipTrigger as-child>
                                        <button
                                            type="button"
                                            class="p-1 text-muted-foreground hover:text-foreground transition-colors rounded"
                                            @click="handleMarkAsRead(notification)"
                                        >
                                            <IconCheck class="size-3.5" />
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent>{{ $t('sidebar.mark_as_read') }}</TooltipContent>
                                </Tooltip>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="!loading" class="flex flex-col items-center justify-center py-12 px-6 text-center">
                        <IconInbox class="size-8 text-muted-foreground/50 mb-3" />
                        <p class="text-sm font-medium">{{ $t('sidebar.no_notifications') }}</p>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
