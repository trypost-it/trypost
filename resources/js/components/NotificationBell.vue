<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { IconArchive, IconBell, IconCheck, IconChecks } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { onMounted, ref } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    SidebarMenuButton,
} from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
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
const dialogOpen = ref(false);

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

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const handleMarkAsRead = async (notification: Notification) => {
    await fetch(read.url(notification.id), {
        method: 'PATCH',
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

    dialogOpen.value = false;

    if (notification.data?.post_id) {
        router.visit(`/posts/${notification.data.post_id}/edit`);
    } else if (notification.data?.social_account_id || notification.data?.workspace_id) {
        router.visit('/accounts');
    }
};

const openDialog = () => {
    dialogOpen.value = true;
    fetchNotifications();
};

onMounted(() => {
    fetchNotifications();
});
</script>

<template>
    <SidebarMenuButton :tooltip="$t('sidebar.notifications')" @click="openDialog">
        <div class="relative">
            <IconBell />
            <span
                v-if="unreadCount > 0"
                class="absolute -top-1 -right-1 flex size-3.5 items-center justify-center rounded-full bg-destructive text-[8px] font-bold text-destructive-foreground"
            >
                {{ unreadCount > 9 ? '9+' : unreadCount }}
            </span>
        </div>
        <span>{{ $t('sidebar.notifications') }}</span>
    </SidebarMenuButton>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <div class="flex items-center justify-between">
                    <DialogTitle>{{ $t('sidebar.notifications') }}</DialogTitle>
                    <div v-if="notifications.length > 0" class="flex items-center gap-1">
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button variant="ghost" size="icon" class="size-7" @click="handleMarkAllAsRead">
                                        <IconChecks class="size-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>{{ $t('sidebar.mark_all_read') }}</TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button variant="ghost" size="icon" class="size-7" @click="handleArchiveAll">
                                        <IconArchive class="size-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>{{ $t('sidebar.archive_all') }}</TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                </div>
            </DialogHeader>

            <div v-if="notifications.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                {{ $t('sidebar.no_notifications') }}
            </div>

            <div v-else class="-mx-6 max-h-96 overflow-y-auto">
                <div
                    v-for="notification in notifications"
                    :key="notification.id"
                    class="flex cursor-pointer items-start gap-3 border-b px-6 py-3 transition-colors last:border-0 hover:bg-accent/50"
                    :class="{ 'opacity-60': notification.read_at }"
                    @click="handleNotificationClick(notification)"
                >
                    <span
                        v-if="!notification.read_at"
                        class="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
                    />
                    <span v-else class="mt-1.5 size-2 shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium leading-tight">{{ notification.title }}</p>
                        <p class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">{{ notification.body }}</p>
                    </div>
                    <Button
                        v-if="!notification.read_at"
                        variant="ghost"
                        size="icon"
                        class="size-7 shrink-0"
                        @click.stop="handleMarkAsRead(notification)"
                    >
                        <IconCheck class="size-3.5" />
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
