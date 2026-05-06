<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    IconAffiliate,
    IconCalendar,
    IconChartBar,
    IconChevronRight,
    IconClock,
    IconFileCheck,
    IconFileText,
    IconHash,
    IconPhoto,
    IconPencil,
    IconPlus,
    IconSettings,
    IconTag,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import { create as createPost, index as postsIndex } from '@/actions/App/Http/Controllers/App/PostController';
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
import { useActiveUrl } from '@/composables/useActiveUrl';
import { useFeatureAccess } from '@/composables/useFeatureAccess';
import { useUpgradeDialog } from '@/composables/useUpgradeDialog';
import { accounts, analytics, calendar, settings as settingsHub } from '@/routes/app';
import { index as assets } from '@/routes/app/assets';
import { index as labels } from '@/routes/app/labels';
import { index as signatures } from '@/routes/app/signatures';
import { create as createWorkspaceRoute, switchMethod } from '@/routes/app/workspaces';
import type { NavItem } from '@/types';

interface Workspace {
    id: string;
    name: string;
    logo_url: string | null;
}

const page = usePage();
const currentWorkspace = computed<Workspace | null>(() => page.props.auth.currentWorkspace as Workspace | null);
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

const workspaceNavItems = computed<NavItem[]>(() => [
    {
        title: trans('sidebar.workspace.connections'),
        href: accounts.url(),
        icon: IconAffiliate,
    },
    {
        title: trans('sidebar.workspace.signatures'),
        href: signatures.url(),
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
]);

const switchWorkspace = (workspaceId: string) => {
    router.post(switchMethod.url(workspaceId), {}, {
        preserveScroll: true,
    });
};

const { canCreateWorkspace } = useFeatureAccess();
const { openUpgrade } = useUpgradeDialog();
const { urlIsActive } = useActiveUrl();

const handleCreateWorkspace = () => {
    if (!canCreateWorkspace.value) {
        openUpgrade(trans('billing.upgrade_dialog.reasons.workspace_limit'));
        return;
    }
    router.visit(createWorkspaceRoute.url());
};
</script>

<template>
    <Sidebar collapsible="icon">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <SidebarMenuButton size="lg"
                                class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground">
                                <Avatar :src="currentWorkspace?.logo_url" :name="currentWorkspace?.name ?? '?'"
                                    class="h-8 w-8 shrink-0 rounded-md border-2 border-foreground"
                                    fallback-class="bg-violet-100 text-violet-700 font-bold" />
                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">
                                        {{ currentWorkspace?.name ?? $t('sidebar.select_workspace') }}
                                    </span>
                                </div>
                                <IconChevronRight class="ml-auto size-4" />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="w-[--reka-dropdown-menu-trigger-width] min-w-56"
                            align="start" side="right" :side-offset="4">
                            <DropdownMenuLabel>
                                {{ $t('sidebar.workspaces') }}
                            </DropdownMenuLabel>
                            <div class="space-y-0.5">
                                <DropdownMenuItem v-for="workspace in workspaces" :key="workspace.id"
                                    :class="workspace.id === currentWorkspace?.id ? 'bg-accent text-accent-foreground' : ''"
                                    @click="switchWorkspace(workspace.id)">
                                    <Avatar :src="workspace.logo_url" :name="workspace.name"
                                        class="h-6 w-6 shrink-0 rounded-md border-2 border-foreground" fallback-class="text-[10px] bg-violet-100 text-violet-700 font-bold" />
                                    {{ workspace.name }}
                                </DropdownMenuItem>
                            </div>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="handleCreateWorkspace">
                                <IconPlus class="size-4" />
                                {{ $t('sidebar.create_workspace') }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <!-- Create Post Button -->
            <div v-if="currentWorkspace" class="px-2 py-2">
                <Link :href="createPost.url()" class="block">
                    <Button :size="sidebarState === 'collapsed' ? 'icon' : 'default'" class="w-full">
                        <IconPlus v-if="sidebarState === 'collapsed'" class="size-4" />
                        <span v-if="sidebarState === 'expanded'">{{ $t('sidebar.create_post') }}</span>
                    </Button>
                </Link>
            </div>

            <NavMain v-if="currentWorkspace" :items="mainNavItems" />
            <NavMain v-if="currentWorkspace" :items="postsNavItems" :label="$t('sidebar.groups.posts')" />
            <NavMain v-if="currentWorkspace" :items="workspaceNavItems" :label="$t('sidebar.groups.workspace')" />
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        as-child
                        :tooltip="trans('sidebar.settings')"
                        :is-active="urlIsActive(settingsHub.url())"
                    >
                        <Link :href="settingsHub.url()">
                            <IconSettings />
                            <span>{{ $t('sidebar.settings') }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>