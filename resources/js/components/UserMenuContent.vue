<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    IconBrightnessUp,
    IconDeviceDesktop,
    IconLogout,
    IconMoon,
    IconUser,
} from '@tabler/icons-vue';

import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuPortal,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { useAppearance } from '@/composables/useAppearance';
import { logout } from '@/routes';
import { edit } from '@/routes/app/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const { appearance, updateAppearance } = useAppearance();

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo
                :user="user"
                :show-email="true"
                fallback-class="bg-neutral-200 text-neutral-600 dark:bg-neutral-700 dark:text-neutral-200"
            />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <IconUser class="size-4" />
                Account
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuSub>
            <DropdownMenuSubTrigger class="gap-2">
                <IconBrightnessUp
                    v-if="appearance === 'light'"
                    class="size-4 text-muted-foreground"
                />
                <IconMoon
                    v-else-if="appearance === 'dark'"
                    class="size-4 text-muted-foreground"
                />
                <IconDeviceDesktop
                    v-else
                    class="size-4 text-muted-foreground"
                />
                Theme: <span class="capitalize">{{ appearance }}</span>
            </DropdownMenuSubTrigger>
            <DropdownMenuPortal>
                <DropdownMenuSubContent>
                    <DropdownMenuItem @click="updateAppearance('light')">
                        <IconBrightnessUp class="size-4" />
                        Light
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="updateAppearance('dark')">
                        <IconMoon class="size-4" />
                        Dark
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="updateAppearance('system')">
                        <IconDeviceDesktop class="size-4" />
                        System
                    </DropdownMenuItem>
                </DropdownMenuSubContent>
            </DropdownMenuPortal>
        </DropdownMenuSub>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <IconLogout class="size-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
