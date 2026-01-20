<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import Heading from '@/components/Heading.vue';
import { useActiveUrl } from '@/composables/useActiveUrl';
import { toUrl } from '@/lib/utils';
import { members } from '@/routes';
import { index as billing } from '@/routes/billing';
import { edit as editProfile } from '@/routes/profile';
import { edit as editPassword } from '@/routes/user-password';
import { settings as workspaceSettings } from '@/routes/workspace';
import { type NavItem, type SharedData } from '@/types';

const page = usePage<SharedData>();
const auth = computed(() => page.props.auth);
const canManageWorkspace = computed(() => auth.value.role !== 'member');

const navItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Profile',
            href: editProfile(),
        },
        {
            title: 'Password',
            href: editPassword(),
        },
    ];

    if (canManageWorkspace.value) {
        items.push(
            {
                title: 'Workspace',
                href: workspaceSettings(),
            },
            {
                title: 'Members',
                href: members(),
            },
        );

        if (!page.props.selfHosted) {
            items.push({
                title: 'Billing',
                href: billing(),
            });
        }
    }

    return items;
});

const { urlIsActive } = useActiveUrl();
</script>

<template>
    <div class="px-4 py-6">
        <div class="flex flex-col items-center gap-6">
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
                class="w-full max-w-2xl"
            />

            <nav
                class="inline-flex h-9 w-full max-w-2xl items-center justify-start rounded-lg bg-muted p-1 text-muted-foreground"
                aria-label="Settings"
            >
                <Link
                    v-for="item in navItems"
                    :key="toUrl(item.href)"
                    :href="item.href"
                    :class="[
                        'inline-flex items-center justify-center whitespace-nowrap rounded-md px-3 py-1 text-sm font-medium ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                        urlIsActive(item.href)
                            ? 'bg-background text-foreground shadow'
                            : 'hover:bg-background/50 hover:text-foreground',
                    ]"
                >
                    {{ item.title }}
                </Link>
            </nav>

            <div class="w-full max-w-2xl">
                <slot />
            </div>
        </div>
    </div>
</template>
