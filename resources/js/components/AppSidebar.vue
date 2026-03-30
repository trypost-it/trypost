<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    IconAffiliate,
    IconCalendar,
    IconChevronRight,
    IconClock,
    IconFileCheck,
    IconFileText,
    IconHash,
    IconLifebuoy,
    IconMessageCircle,
    IconPencil,
    IconPlus,
    IconSettings,
    IconTag,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import { store as storePost } from '@/actions/App/Http/Controllers/App/PostController';
import { index as postsIndex } from '@/actions/App/Http/Controllers/App/PostController';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { accounts, calendar } from '@/routes/app';
import { index as hashtags } from '@/routes/app/hashtags';
import { index as labels } from '@/routes/app/labels';
import { edit as editProfile } from '@/routes/app/profile';
import { settings as workspaceSettings } from '@/routes/app/workspace';
import { create as createWorkspaceRoute, switchMethod } from '@/routes/app/workspaces';
import type { NavItem } from '@/types';

interface Workspace {
    id: string;
    name: string;
    logo_url: string | null;
}

const page = usePage();
const auth = computed(() => page.props.auth);
const currentWorkspace = computed<Workspace | null>(() => page.props.auth.currentWorkspace as Workspace | null);
const workspaces = computed<Workspace[]>(() => page.props.auth.workspaces as Workspace[]);

const { state: sidebarState } = useSidebar();

const postsNavItems = computed<NavItem[]>(() => [
    {
        title: trans('sidebar.posts.calendar'),
        href: calendar.url(),
        icon: IconCalendar,
    },
    {
        title: trans('sidebar.posts.all'),
        href: postsIndex.url(),
        icon: IconFileText,
        excludeActive: [postsIndex.url('scheduled'), postsIndex.url('published'), postsIndex.url('draft')],
    },
    {
        title: trans('sidebar.posts.scheduled'),
        href: postsIndex.url('scheduled'),
        icon: IconClock,
    },
    {
        title: trans('sidebar.posts.posted'),
        href: postsIndex.url('published'),
        icon: IconFileCheck,
    },
    {
        title: trans('sidebar.posts.drafts'),
        href: postsIndex.url('draft'),
        icon: IconPencil,
    },
]);

const canManageWorkspace = computed(() => auth.value.currentWorkspace?.role !== 'member');

const configNavItems = computed(() => {
    const items: NavItem[] = [
        {
            title: trans('sidebar.config.connections'),
            href: accounts.url(),
            icon: IconAffiliate,
        },
        {
            title: trans('sidebar.config.hashtags'),
            href: hashtags.url(),
            icon: IconHash,
        },
        {
            title: trans('sidebar.config.labels'),
            href: labels.url(),
            icon: IconTag,
        },
    ];

    if (canManageWorkspace.value) {
        items.push({
            title: trans('sidebar.config.settings'),
            href: editProfile.url(),
            icon: IconSettings,
            activePattern: '/settings',
        });
    }

    return items;
});

const switchWorkspace = (workspaceId: string) => {
    router.post(switchMethod.url(workspaceId), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <SidebarMenuButton size="lg"
                                class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground">
                                <Avatar :src="currentWorkspace?.logo_url" :name="currentWorkspace?.name ?? '?'"
                                    class="h-8 w-8 shrink-0 rounded-lg"
                                    fallback-class="bg-sidebar-accent text-sidebar-accent-foreground" />
                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">
                                        {{ currentWorkspace?.name ?? $t('sidebar.select_workspace') }}
                                    </span>
                                </div>
                                <IconChevronRight class="ml-auto size-4" />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="w-[--reka-dropdown-menu-trigger-width] min-w-56 rounded-lg"
                            align="start" side="right" :side-offset="4">
                            <DropdownMenuLabel class="text-xs text-muted-foreground">
                                {{ $t('sidebar.workspaces') }}
                            </DropdownMenuLabel>
                            <div class="space-y-0.5">
                                <DropdownMenuItem v-for="workspace in workspaces" :key="workspace.id" class="gap-2"
                                    :class="workspace.id === currentWorkspace?.id ? 'bg-accent' : ''"
                                    @click="switchWorkspace(workspace.id)">
                                    <Avatar :src="workspace.logo_url" :name="workspace.name"
                                        class="h-5 w-5 shrink-0 rounded-md" fallback-class="text-[10px]" />
                                    {{ workspace.name }}
                                </DropdownMenuItem>
                            </div>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem as-child>
                                <Link :href="createWorkspaceRoute.url()">
                                    <IconPlus class="size-4" />
                                    {{ $t('sidebar.create_workspace') }}
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <!-- Create Post Button -->
            <div v-if="currentWorkspace" class="px-2 py-2">
                <Link :href="storePost.url()" method="post" class="w-full">
                    <Button :size="sidebarState === 'collapsed' ? 'icon' : 'default'" class="w-full">
                        <IconPlus v-if="sidebarState === 'collapsed'" class="size-4" />
                        <span v-if="sidebarState === 'expanded'">{{ $t('sidebar.create_post') }}</span>
                    </Button>
                </Link>
            </div>

            <NavMain v-if="currentWorkspace" :items="postsNavItems" :label="$t('sidebar.groups.posts')" />
            <NavMain v-if="currentWorkspace" :items="configNavItems" :label="$t('sidebar.groups.configuration')" />
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton as-child tooltip="Feedback">
                        <a href="https://github.com/trypost-it/trypost/discussions" target="_blank"
                            rel="noopener noreferrer">
                            <IconMessageCircle />
                            <span>{{ $t('sidebar.support.share_feedback') }}</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
                <SidebarMenuItem>
                    <SidebarMenuButton as-child tooltip="Docs">
                        <a href="https://trypost.it/docs" target="_blank" rel="noopener noreferrer">
                            <IconLifebuoy />
                            <span>{{ $t('sidebar.support.docs') }}</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <div class="flex items-center gap-1">
                <div class="flex-1">
                    <NavUser />
                </div>
                <NotificationBell v-if="currentWorkspace" />
            </div>
        </SidebarFooter>
    </Sidebar>
</template>