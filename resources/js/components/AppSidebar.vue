<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { IconBrandDiscord, IconCalendar, IconCheck, IconSelector, IconFileText, IconHash, IconLogout, IconPlus, IconSettings, IconAffiliate, IconPencil, IconFileCheck, IconTag, IconUser, IconClock, IconMessageCircle, IconBell, IconBook, IconSun, IconMoon, IconDeviceDesktop, IconLanguage } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { Button } from '@/components/ui/button';
import { create as createPost } from '@/actions/App/Http/Controllers/PostController';
import { updateLanguage } from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { computed } from 'vue';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuPortal,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
    useSidebar,
} from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';
import { useInitials } from '@/composables/useInitials';
import { accounts, calendar, logout } from '@/routes';
import { index as hashtags } from '@/routes/hashtags';
import { index as labels } from '@/routes/labels';
import { index as postsIndex } from '@/actions/App/Http/Controllers/PostController';
import { edit as editProfile } from '@/routes/profile';
import { settings as workspaceSettings } from '@/routes/workspace';
import { create as createWorkspaceRoute, switchMethod } from '@/routes/workspaces';
import type { NavItem } from '@/types';

interface Workspace {
    id: string;
    name: string;
    logo: {
        url: string;
        media_id: string | null;
    };
}

interface Language {
    id: string;
    name: string;
    code: string;
}

const page = usePage();
const auth = computed(() => page.props.auth);
const currentWorkspace = computed<Workspace | null>(() => page.props.currentWorkspace as Workspace | null);
const workspaces = computed<Workspace[]>(() => page.props.workspaces as Workspace[]);
const languages = computed<Language[]>(() => page.props.languages as Language[]);
const currentLanguage = computed(() => languages.value.find(l => l.id === auth.value.user.language_id));

const { getInitials } = useInitials();
const { appearance, updateAppearance } = useAppearance();
const { state: sidebarState } = useSidebar();

const themeLabels = computed(() => ({
    light: trans('sidebar.theme_light'),
    dark: trans('sidebar.theme_dark'),
    system: trans('sidebar.theme_system'),
}));

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

const canManageWorkspace = computed(() => auth.value.role !== 'member');

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
            href: workspaceSettings.url(),
            icon: IconSettings,
        });
    }

    return items;
});

const supportNavItems = computed<NavItem[]>(() => [
    {
        title: trans('sidebar.support.discord'),
        href: 'https://trypost.it/discord',
        icon: IconBrandDiscord,
    },
    {
        title: trans('sidebar.support.share_feedback'),
        href: 'https://github.com/trypost-it/trypost/discussions',
        icon: IconMessageCircle,
    },
    {
        title: trans('sidebar.support.last_updates'),
        href: 'https://github.com/trypost-it/trypost/releases',
        icon: IconBell,
    },
    {
        title: trans('sidebar.support.docs'),
        href: 'https://docs.trypost.it',
        icon: IconBook,
    },
]);

function switchWorkspace(workspace: Workspace) {
    router.post(switchMethod.url(workspace.id), {}, {
        preserveScroll: true,
    });
}

function switchLanguage(languageId: string) {
    router.patch(updateLanguage.url(), { language_id: languageId }, {
        preserveScroll: true,
    });
}

function createWorkspace() {
    router.visit(createWorkspaceRoute.url());
}

function isActive(href: string): boolean {
    // Exact match or match with query string
    return page.url === href || page.url.startsWith(href + '?');
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
                            <SidebarMenuButton size="lg"
                                class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground">
                                <Avatar class="h-9 w-9 rounded-full shrink-0">
                                    <AvatarImage v-if="auth.user.avatar?.url" :src="auth.user.avatar?.url"
                                        :alt="auth.user.name" />
                                    <AvatarFallback class="rounded-full text-xs">
                                        {{ getInitials(auth.user.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth.user.name }}</span>
                                    <span class="truncate text-xs text-muted-foreground">{{ currentWorkspace?.name ||
                                        $t('sidebar.select_workspace') }}</span>
                                </div>
                                <IconSelector class="ml-auto size-4" />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="w-[--reka-dropdown-menu-trigger-width] min-w-56 rounded-lg"
                            align="start" side="bottom" :side-offset="4">
                            <!-- User Info -->
                            <DropdownMenuLabel class="p-0 font-normal">
                                <div class="flex items-center gap-2 px-2 py-2 text-left text-sm">
                                    <Avatar class="h-8 w-8 rounded-full">
                                        <AvatarImage v-if="auth.user.avatar?.url" :src="auth.user.avatar?.url"
                                            :alt="auth.user.name" />
                                        <AvatarFallback class="rounded-full">
                                            {{ getInitials(auth.user.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div class="grid flex-1 text-left text-sm leading-tight">
                                        <span class="truncate font-semibold">{{ auth.user.name }}</span>
                                        <span class="truncate text-xs text-muted-foreground">{{ auth.user.email
                                            }}</span>
                                    </div>
                                </div>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />

                            <!-- Workspaces -->
                            <DropdownMenuSub>
                                <DropdownMenuSubTrigger>
                                    <img v-if="currentWorkspace?.logo.url" :src="currentWorkspace.logo.url"
                                        :alt="currentWorkspace.name" class="mr-2 size-4 rounded-full object-cover" />
                                    <span>{{ currentWorkspace ? $t('sidebar.workspace', { name: currentWorkspace.name }) : $t('sidebar.workspace_select') }}</span>
                                </DropdownMenuSubTrigger>
                                <DropdownMenuPortal>
                                    <DropdownMenuSubContent>
                                        <DropdownMenuRadioGroup :model-value="currentWorkspace?.id"
                                            @update:model-value="(id: string) => switchWorkspace(workspaces.find(w => w.id === id)!)">
                                            <DropdownMenuRadioItem v-for="workspace in workspaces" :key="workspace.id"
                                                :value="workspace.id">
                                                <img :src="workspace.logo.url" :alt="workspace.name"
                                                    class="mr-2 size-4 rounded-full object-cover" />
                                                <span>{{ workspace.name }}</span>
                                            </DropdownMenuRadioItem>
                                        </DropdownMenuRadioGroup>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem class="cursor-pointer gap-2" @click="createWorkspace">
                                            <IconPlus class="size-4" />
                                            <span>{{ $t('sidebar.create_workspace') }}</span>
                                        </DropdownMenuItem>
                                    </DropdownMenuSubContent>
                                </DropdownMenuPortal>
                            </DropdownMenuSub>

                            <DropdownMenuSeparator />

                            <!-- Theme -->
                            <DropdownMenuSub>
                                <DropdownMenuSubTrigger>
                                    <IconSun v-if="appearance === 'light'" class="mr-2 size-4" />
                                    <IconMoon v-else-if="appearance === 'dark'" class="mr-2 size-4" />
                                    <IconDeviceDesktop v-else class="mr-2 size-4" />
                                    <span>{{ $t('sidebar.theme', { name: themeLabels[appearance] }) }}</span>
                                </DropdownMenuSubTrigger>
                                <DropdownMenuPortal>
                                    <DropdownMenuSubContent>
                                        <DropdownMenuRadioGroup :model-value="appearance"
                                            @update:model-value="updateAppearance">
                                            <DropdownMenuRadioItem value="light">
                                                <IconSun class="mr-2 size-4" />
                                                <span>{{ $t('sidebar.theme_light') }}</span>
                                            </DropdownMenuRadioItem>
                                            <DropdownMenuRadioItem value="dark">
                                                <IconMoon class="mr-2 size-4" />
                                                <span>{{ $t('sidebar.theme_dark') }}</span>
                                            </DropdownMenuRadioItem>
                                            <DropdownMenuRadioItem value="system">
                                                <IconDeviceDesktop class="mr-2 size-4" />
                                                <span>{{ $t('sidebar.theme_system') }}</span>
                                            </DropdownMenuRadioItem>
                                        </DropdownMenuRadioGroup>
                                    </DropdownMenuSubContent>
                                </DropdownMenuPortal>
                            </DropdownMenuSub>

                            <DropdownMenuSeparator />

                            <!-- Language -->
                            <DropdownMenuSub>
                                <DropdownMenuSubTrigger>
                                    <IconLanguage class="mr-2 size-4" />
                                    <span>{{ currentLanguage ? $t('sidebar.language', { name: currentLanguage.name }) : $t('sidebar.language_select') }}</span>
                                </DropdownMenuSubTrigger>
                                <DropdownMenuPortal>
                                    <DropdownMenuSubContent>
                                        <DropdownMenuRadioGroup :model-value="currentLanguage?.id"
                                            @update:model-value="switchLanguage">
                                            <DropdownMenuRadioItem v-for="language in languages" :key="language.id"
                                                :value="language.id">
                                                <span>{{ language.name }}</span>
                                            </DropdownMenuRadioItem>
                                        </DropdownMenuRadioGroup>
                                    </DropdownMenuSubContent>
                                </DropdownMenuPortal>
                            </DropdownMenuSub>

                            <DropdownMenuSeparator />

                            <!-- User Actions -->
                            <DropdownMenuGroup>
                                <DropdownMenuItem as-child>
                                    <Link class="cursor-pointer" :href="editProfile()">
                                        <IconUser class="mr-2 size-4" />
                                        {{ $t('sidebar.profile') }}
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuGroup>

                            <DropdownMenuSeparator />


                            <DropdownMenuItem as-child>
                                <Link class="w-full cursor-pointer" :href="logout()" @click="handleLogout" as="button">
                                    <IconLogout class="mr-2 size-4" />
                                    {{ $t('sidebar.log_out') }}
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
                <Link :href="createPost.url()">
                    <Button :size="sidebarState === 'collapsed' ? 'icon' : 'default'" class="w-full">
                        <IconPlus class="size-4" />
                        <span v-if="sidebarState === 'expanded'">{{ $t('sidebar.create_post') }}</span>
                    </Button>
                </Link>
            </div>

            <SidebarGroup v-if="currentWorkspace">
                <SidebarGroupLabel>{{ $t('sidebar.groups.posts') }}</SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in postsNavItems" :key="item.title">
                            <SidebarMenuButton as-child :tooltip="item.title"
                                :is-active="isActive(item.href as string)">
                                <Link :href="item.href">
                                    <component v-if="item.icon" :is="item.icon" />
                                    <span>{{ item.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <SidebarGroup v-if="currentWorkspace">
                <SidebarGroupLabel>{{ $t('sidebar.groups.configuration') }}</SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in configNavItems" :key="item.title">
                            <SidebarMenuButton as-child :tooltip="item.title"
                                :is-active="isActive(item.href as string)">
                                <Link :href="item.href">
                                    <component v-if="item.icon" :is="item.icon" />
                                    <span>{{ item.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <SidebarGroup v-if="currentWorkspace">
                <SidebarGroupLabel>{{ $t('sidebar.groups.support') }}</SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in supportNavItems" :key="item.title">
                            <SidebarMenuButton as-child :tooltip="item.title">
                                <a :href="item.href"
                                    :target="(item.href as string).startsWith('http') ? '_blank' : undefined">
                                    <component v-if="item.icon" :is="item.icon" />
                                    <span>{{ item.title }}</span>
                                </a>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </SidebarContent>

        <SidebarRail />
    </Sidebar>
</template>