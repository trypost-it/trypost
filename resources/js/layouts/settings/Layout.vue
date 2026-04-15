<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import { useActiveUrl } from '@/composables/useActiveUrl';
import { toUrl } from '@/lib/utils';
import { preferences as notificationPreferences } from '@/routes/app/notifications';
import { edit as editProfile } from '@/routes/app/profile';
import { edit as editPassword } from '@/routes/app/user-password';
import { type NavItem } from '@/types';

const navItems = computed<NavItem[]>(() => [
    {
        title: trans('settings.nav.profile'),
        href: editProfile(),
    },
    {
        title: trans('settings.nav.password'),
        href: editPassword(),
    },
    {
        title: trans('settings.nav.notifications'),
        href: notificationPreferences(),
    },
]);

const { urlIsActive } = useActiveUrl();
</script>

<template>
    <div class="mx-auto max-w-4xl px-4 py-6">
        <nav
            class="mb-8 inline-flex h-9 w-fit items-center justify-center rounded-lg bg-muted p-1 text-muted-foreground">
            <Link v-for="item in navItems" :key="toUrl(item.href)" :href="item.href"
                class="inline-flex items-center justify-center rounded-md px-3 py-1 text-sm font-medium whitespace-nowrap transition-all focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                :class="urlIsActive(item.href)
                    ? 'bg-background text-foreground shadow-sm'
                    : 'hover:bg-background/50 hover:text-foreground'
                    ">
                {{ item.title }}
            </Link>
        </nav>

        <section class="space-y-12">
            <slot />
        </section>
    </div>
</template>
