<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { IconAlertTriangle, IconArrowRight, IconSettings } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';

import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { accounts, members, subscribe } from '@/routes/app';
import { index as workspacesIndex } from '@/routes/app/workspaces';
import { index as postsIndex } from '@/routes/app/posts';

interface Violation {
    dimension: 'social_accounts' | 'workspaces' | 'members' | 'scheduled_posts';
    current: number;
    limit: number;
    label_key: string;
    manage_route: string;
}

const props = defineProps<{
    violations: Violation[];
    planName: string | null;
}>();

const manageUrl = (violation: Violation): string => {
    const map: Record<string, string> = {
        social_accounts: accounts.url(),
        workspaces: workspacesIndex.url(),
        members: members.url(),
        scheduled_posts: postsIndex.url(),
    };
    return map[violation.dimension] ?? '/';
};
</script>

<template>
    <Head :title="$t('compliance.title')" />

    <AppLayout>
        <div class="mx-auto max-w-2xl space-y-8 px-4 py-12">
            <!-- Header -->
            <div class="flex flex-col items-center gap-4 text-center">
                <div class="flex size-14 items-center justify-center rounded-full border-2 border-amber-400 bg-amber-50">
                    <IconAlertTriangle class="size-7 text-amber-500" stroke-width="2" />
                </div>
                <div class="space-y-2">
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">
                        {{ $t('compliance.title') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ trans('compliance.description', { plan: props.planName ?? 'Free' }) }}
                    </p>
                </div>
            </div>

            <!-- Violation cards -->
            <div class="space-y-3">
                <div
                    v-for="violation in props.violations"
                    :key="violation.dimension"
                    class="flex items-center justify-between rounded-xl border-2 border-foreground/10 bg-card px-5 py-4"
                >
                    <div class="space-y-0.5">
                        <p class="text-sm font-semibold text-foreground">
                            {{ $t(violation.label_key) }}
                        </p>
                        <p class="text-sm text-destructive">
                            {{ trans('compliance.usage', { current: violation.current, limit: violation.limit }) }}
                        </p>
                    </div>
                    <Link
                        :href="manageUrl(violation)"
                        class="inline-flex items-center gap-1.5 rounded-lg border-2 border-foreground bg-card px-3 py-1.5 text-xs font-semibold text-foreground transition-colors hover:bg-foreground/5"
                    >
                        <IconSettings class="size-3.5" stroke-width="2" />
                        {{ $t('compliance.manage') }}
                    </Link>
                </div>
            </div>

            <!-- Upgrade CTA -->
            <div class="flex flex-col items-center gap-3">
                <p class="text-xs text-muted-foreground">
                    {{ $t('compliance.upgrade_hint') }}
                </p>
                <Button as-child class="gap-2">
                    <Link :href="subscribe.url()">
                        {{ $t('compliance.upgrade_cta') }}
                        <IconArrowRight class="size-4" />
                    </Link>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
