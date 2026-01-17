<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Calendar, Check, ChevronsUpDown, CreditCard, LogOut, Plus, Settings, Share2, Sparkles, Users } from 'lucide-vue-next';
import { computed } from 'vue';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/components/ui/sidebar';
import { useInitials } from '@/composables/useInitials';
import { accounts, calendar, logout, members, settings } from '@/routes';
import { index as billing } from '@/routes/billing';
import { edit as editProfile } from '@/routes/profile';
import { create as createWorkspaceRoute, switchMethod } from '@/routes/workspaces';
import type { NavItem } from '@/types';

interface Workspace {
    id: string;
    name: string;
}

const page = usePage();
const auth = computed(() => page.props.auth);
const currentWorkspace = computed<Workspace | null>(() => page.props.currentWorkspace as Workspace | null);
const workspaces = computed<Workspace[]>(() => page.props.workspaces as Workspace[]);

const { getInitials } = useInitials();

const mainNavItems: NavItem[] = [
    {
        title: 'Calendar',
        href: calendar.url(),
        icon: Calendar,
    },
    {
        title: 'Accounts',
        href: accounts.url(),
        icon: Share2,
    },
    {
        title: 'Members',
        href: members.url(),
        icon: Users,
    },
    {
        title: 'Settings',
        href: settings.url(),
        icon: Settings,
    },
];

function switchWorkspace(workspace: Workspace) {
    router.post(switchMethod.url(workspace.id), {}, {
        preserveScroll: true,
    });
}

function createWorkspace() {
    router.visit(createWorkspaceRoute.url());
}

function isActive(href: string): boolean {
    return page.url.startsWith(href);
}

function handleLogout() {
    router.flushAll();
}
</script>

<template>
    <Sidebar collapsible="icon">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <SidebarMenuButton
                                size="lg"
                                class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                            >
                                <div class="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                    <Sparkles class="size-4" />
                                </div>
                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ currentWorkspace?.name || 'Select workspace' }}</span>
                                    <span class="truncate text-xs text-muted-foreground">Workspace</span>
                                </div>
                                <ChevronsUpDown class="ml-auto size-4" />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            class="w-[--reka-dropdown-menu-trigger-width] min-w-56 rounded-lg"
                            align="start"
                            side="bottom"
                            :side-offset="4"
                        >
                            <DropdownMenuLabel class="text-xs text-muted-foreground">
                                Workspaces
                            </DropdownMenuLabel>
                            <DropdownMenuItem
                                v-for="workspace in workspaces"
                                :key="workspace.id"
                                class="cursor-pointer gap-2 p-2"
                                @click="switchWorkspace(workspace)"
                            >
                                <div class="flex size-6 items-center justify-center rounded-sm border bg-background">
                                    <Sparkles class="size-4 shrink-0" />
                                </div>
                                <span class="truncate">{{ workspace.name }}</span>
                                <Check v-if="currentWorkspace?.id === workspace.id" class="ml-auto size-4" />
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem class="cursor-pointer gap-2 p-2" @click="createWorkspace">
                                <div class="flex size-6 items-center justify-center rounded-md border bg-background">
                                    <Plus class="size-4" />
                                </div>
                                <span class="font-medium">Create workspace</span>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup v-if="currentWorkspace">
                <SidebarGroupLabel>Menu</SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in mainNavItems" :key="item.title">
                            <SidebarMenuButton
                                as-child
                                :tooltip="item.title"
                                :is-active="isActive(item.href as string)"
                            >
                                <Link :href="item.href">
                                    <component v-if="item.icon" :is="item.icon" />
                                    <span>{{ item.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu>
                <SidebarMenuItem>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <SidebarMenuButton
                                size="lg"
                                class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                            >
                                <Avatar class="h-8 w-8 rounded-lg">
                                    <AvatarImage v-if="auth.user.avatar" :src="auth.user.avatar" :alt="auth.user.name" />
                                    <AvatarFallback class="rounded-lg">
                                        {{ getInitials(auth.user.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth.user.name }}</span>
                                    <span class="truncate text-xs text-muted-foreground">{{ auth.user.email }}</span>
                                </div>
                                <ChevronsUpDown class="ml-auto size-4" />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            class="w-[--reka-dropdown-menu-trigger-width] min-w-56 rounded-lg"
                            side="bottom"
                            align="end"
                            :side-offset="4"
                        >
                            <DropdownMenuLabel class="p-0 font-normal">
                                <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                    <Avatar class="h-8 w-8 rounded-lg">
                                        <AvatarImage v-if="auth.user.avatar" :src="auth.user.avatar" :alt="auth.user.name" />
                                        <AvatarFallback class="rounded-lg">
                                            {{ getInitials(auth.user.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div class="grid flex-1 text-left text-sm leading-tight">
                                        <span class="truncate font-semibold">{{ auth.user.name }}</span>
                                        <span class="truncate text-xs text-muted-foreground">{{ auth.user.email }}</span>
                                    </div>
                                </div>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuGroup>
                                <DropdownMenuItem as-child>
                                    <Link class="cursor-pointer" :href="editProfile()">
                                        <Settings class="mr-2 size-4" />
                                        Account Settings
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem as-child>
                                    <Link class="cursor-pointer" :href="billing.url()">
                                        <CreditCard class="mr-2 size-4" />
                                        Billing
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuGroup>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem as-child>
                                <Link
                                    class="cursor-pointer"
                                    :href="logout()"
                                    @click="handleLogout"
                                    as="button"
                                >
                                    <LogOut class="mr-2 size-4" />
                                    Log out
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>

        <SidebarRail />
    </Sidebar>
</template>
