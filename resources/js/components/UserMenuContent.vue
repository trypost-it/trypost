<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    IconBrightnessUp,
    IconDeviceDesktop,
    IconLanguage,
    IconLogout,
    IconMoon,
    IconUser,
} from '@tabler/icons-vue';
import { loadLanguageAsync } from 'laravel-vue-i18n';
import { computed } from 'vue';

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
import dayjs from '@/dayjs';
import { logout } from '@/routes';
import { edit } from '@/routes/app/profile';
import { updateLanguage } from '@/actions/App/Http/Controllers/App/Settings/ProfileController';
import type { User } from '@/types';

interface Language {
    code: string;
    name: string;
}

type Props = {
    user: User;
};

defineProps<Props>();

const page = usePage();
const languages = computed<Language[]>(() => page.props.languages as Language[]);
const currentLanguage = computed(() => languages.value?.find((l: Language) => l.code === page.props.auth.user?.locale));

const { appearance, updateAppearance } = useAppearance();

const switchLanguage = (code: string) => {
    const previousCode = currentLanguage.value?.code || 'en';

    loadLanguageAsync(code);
    dayjs.locale(code.toLowerCase());

    router.patch(updateLanguage.url(), { locale: code }, {
        preserveScroll: true,
        preserveState: false,
        onError: () => {
            loadLanguageAsync(previousCode);
            dayjs.locale(previousCode.toLowerCase());
        },
    });
};

const handleLogout = () => {
    router.flushAll();
};
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
    <DropdownMenuGroup>
        <DropdownMenuSub v-if="languages && languages.length > 1">
            <DropdownMenuSubTrigger class="gap-2">
                <IconLanguage class="size-4 text-muted-foreground" />
                Language: <span>{{ currentLanguage?.name ?? 'English' }}</span>
            </DropdownMenuSubTrigger>
            <DropdownMenuPortal>
                <DropdownMenuSubContent>
                    <DropdownMenuItem
                        v-for="language in languages"
                        :key="language.code"
                        :class="language.code === currentLanguage?.code ? 'bg-accent' : ''"
                        @click="switchLanguage(language.code)"
                    >
                        {{ language.name }}
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
