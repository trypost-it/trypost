<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link, usePage } from '@inertiajs/vue3';
import { IconCalendar, IconMenu2, IconSettings, IconShare, IconUsers } from '@tabler/icons-vue';
import { computed } from 'vue';

import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import WorkspaceSwitcher from '@/components/WorkspaceSwitcher.vue';
import { useActiveUrl } from '@/composables/useActiveUrl';
import { getInitials } from '@/composables/useInitials';
import { calendar } from '@/routes';
import type { BreadcrumbItem, NavItem } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItem[];
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth);
const currentWorkspace = computed(() => page.props.currentWorkspace);
const { urlIsActive } = useActiveUrl();

function activeItemStyles(url: NonNullable<InertiaLinkProps['href']>) {
    return urlIsActive(url)
        ? 'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100'
        : '';
}

const mainNavItems: NavItem[] = [
    {
        title: 'Calendar',
        href: '/calendar',
        icon: IconCalendar,
    },
    {
        title: 'Accounts',
        href: '/accounts',
        icon: IconShare,
    },
    {
        title: 'Members',
        href: '/members',
        icon: IconUsers,
    },
    {
        title: 'Settings',
        href: '/settings',
        icon: IconSettings,
    },
];
</script>

<template>
    <div>
        <div class="border-b border-border">
            <div class="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
                <!-- Mobile Menu -->
                <div v-if="currentWorkspace" class="lg:hidden">
                    <Sheet>
                        <SheetTrigger :as-child="true">
                            <Button variant="ghost" size="icon" class="mr-2 h-9 w-9">
                                <IconMenu2 class="h-5 w-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="w-[300px] p-6">
                            <SheetTitle class="sr-only">Navigation Menu</SheetTitle>
                            <SheetHeader class="flex justify-start text-left">
                                <img src="/images/trypost/logo-light.png" alt="TryPost"
                                    class="dark:hidden h-8 w-auto" />
                                <img src="/images/trypost/logo-dark.png" alt="TryPost"
                                    class="hidden dark:block h-8 w-auto" />
                            </SheetHeader>
                            <div class="flex h-full flex-1 flex-col justify-between space-y-4 py-6">
                                <div class="space-y-4">
                                    <WorkspaceSwitcher />
                                    <nav class="-mx-3 space-y-1">
                                        <Link v-for="item in mainNavItems" :key="item.title" :href="item.href"
                                            class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent"
                                            :class="activeItemStyles(item.href)">
                                            <component v-if="item.icon" :is="item.icon" class="h-5 w-5" />
                                            {{ item.title }}
                                        </Link>
                                    </nav>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>

                <Link :href="calendar.url()" class="flex items-center gap-x-2">
                    <img src="/images/trypost/logo-light.png" alt="TryPost" class="dark:hidden h-8 w-auto" />
                    <img src="/images/trypost/logo-dark.png" alt="TryPost" class="hidden dark:block h-8 w-auto" />
                </Link>

                <!-- Workspace Switcher - Desktop -->
                <div v-if="currentWorkspace" class="ml-6 hidden lg:block">
                    <WorkspaceSwitcher />
                </div>

                <!-- Desktop Menu -->
                <div v-if="currentWorkspace" class="hidden h-full lg:flex lg:flex-1">
                    <NavigationMenu class="ml-6 flex h-full items-stretch">
                        <NavigationMenuList class="flex h-full items-stretch space-x-2">
                            <NavigationMenuItem v-for="(item, index) in mainNavItems" :key="index"
                                class="relative flex h-full items-center">
                                <Link :class="[
                                    navigationMenuTriggerStyle(),
                                    activeItemStyles(item.href),
                                    'h-9 cursor-pointer px-3',
                                ]" :href="item.href">
                                    <component v-if="item.icon" :is="item.icon" class="mr-2 h-4 w-4" />
                                    {{ item.title }}
                                </Link>
                                <div v-if="urlIsActive(item.href)"
                                    class="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-black dark:bg-white">
                                </div>
                            </NavigationMenuItem>
                        </NavigationMenuList>
                    </NavigationMenu>
                </div>

                <div class="ml-auto flex items-center space-x-2">
                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button variant="ghost" size="icon"
                                class="relative size-10 w-auto rounded-full p-1 focus-within:ring-2 focus-within:ring-primary">
                                <Avatar class="size-8 overflow-hidden rounded-full">
                                    <AvatarImage v-if="auth.user.avatar?.url" :src="auth.user.avatar?.url"
                                        :alt="auth.user.name" />
                                    <AvatarFallback
                                        class="rounded-lg bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white">
                                        {{ getInitials(auth.user?.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="auth.user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>

        <div v-if="props.breadcrumbs.length > 1" class="flex w-full border-b border-border">
            <div class="mx-auto flex h-12 w-full items-center justify-start px-4 text-neutral-500 md:max-w-7xl">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
    </div>
</template>