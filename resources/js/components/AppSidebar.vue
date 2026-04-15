<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    IconAffiliate,
    IconCalendar,
    IconChartBar,
    IconChevronRight,
    IconClock,
    IconChartPie,
    IconCreditCard,
    IconFileCheck,
    IconFileText,
    IconHash,
    IconKey,
    IconLifebuoy,
    IconPhoto,
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
import { WorkspaceRole } from '@/enums/workspace-role';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
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
import { accounts, analytics, calendar } from '@/routes/app';
import { edit as accountSettings } from '@/routes/app/account';
import { index as apiKeys } from '@/routes/app/api-keys';
import { index as billing } from '@/routes/app/billing';
import { index as usage } from '@/routes/app/usage';
import { index as assets } from '@/routes/app/assets';
import { index as hashtags } from '@/routes/app/hashtags';
import { index as labels } from '@/routes/app/labels';
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
const isSelfHosted = computed(() => page.props.selfHosted as boolean);
const workspaces = computed<Workspace[]>(() => page.props.auth.workspaces as Workspace[]);

const { state: sidebarState } = useSidebar();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: trans('sidebar.posts.calendar'),
        href: calendar.url(),
        icon: IconCalendar,
    },
    {
        title: trans('sidebar.analytics'),
        href: analytics.url(),
        icon: IconChartBar,
    },
]);

const postsNavItems = computed<NavItem[]>(() => [
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

const isOwner = computed(() => {
    const role = auth.value.currentWorkspace?.role;
    return role === WorkspaceRole.Owner;
});

const workspaceNavItems = computed<NavItem[]>(() => [
    {
        title: trans('sidebar.workspace.connections'),
        href: accounts.url(),
        icon: IconAffiliate,
    },
    {
        title: trans('sidebar.workspace.hashtags'),
        href: hashtags.url(),
        icon: IconHash,
    },
    {
        title: trans('sidebar.workspace.labels'),
        href: labels.url(),
        icon: IconTag,
    },
    {
        title: trans('sidebar.workspace.assets'),
        href: assets.url(),
        icon: IconPhoto,
    },
    {
        title: trans('sidebar.workspace.api_keys'),
        href: apiKeys.url(),
        icon: IconKey,
    },
    {
        title: trans('sidebar.workspace.settings'),
        href: workspaceSettings.url(),
        icon: IconSettings,
    },
]);

const accountNavItems = computed<NavItem[]>(() => [
    {
        title: trans('sidebar.account.settings'),
        href: accountSettings.url(),
        icon: IconSettings,
    },
    {
        title: trans('sidebar.account.usage'),
        href: usage.url(),
        icon: IconChartPie,
    },
    {
        title: trans('sidebar.account.billing'),
        href: billing.url(),
        icon: IconCreditCard,
    },
]);

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

            <NavMain v-if="currentWorkspace" :items="mainNavItems" />
            <NavMain v-if="currentWorkspace" :items="postsNavItems" :label="$t('sidebar.groups.posts')" />
            <NavMain v-if="currentWorkspace" :items="workspaceNavItems" :label="$t('sidebar.groups.workspace')" />
            <NavMain v-if="currentWorkspace && isOwner && !isSelfHosted" :items="accountNavItems" :label="$t('sidebar.groups.account')" />
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton as-child :tooltip="trans('sidebar.support.share_feedback')">
                        <a href="https://github.com/trypost-it/trypost/discussions" target="_blank"
                            rel="noopener noreferrer">
                            <IconMessageCircle />
                            <span>{{ $t('sidebar.support.share_feedback') }}</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
                <SidebarMenuItem>
                    <SidebarMenuButton as-child :tooltip="trans('sidebar.support.docs')">
                        <a href="https://trypost.it/docs" target="_blank" rel="noopener noreferrer">
                            <IconLifebuoy />
                            <span>{{ $t('sidebar.support.docs') }}</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>