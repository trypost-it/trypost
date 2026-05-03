<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UsageMetricRow from '@/components/settings/UsageMetricRow.vue';
import AppLayout from '@/layouts/AppLayout.vue';
interface Plan {
    name: string;
    slug: string;
}

interface Usage {
    workspaceCount: number;
    workspaceLimit: number;
    socialAccountCount: number;
    socialAccountLimit: number;
    memberCount: number;
    memberLimit: number;
    aiImagesUsed: number;
    aiImagesLimit: number;
    aiTextUsed: number;
}

const props = defineProps<{
    plan: Plan | null;
    usage: Usage;
}>();
</script>

<template>
    <Head :title="$t('usage.title')" />

    <AppLayout :title="$t('usage.title')">
        <div class="mx-auto max-w-3xl space-y-0 p-6">
            <!-- Account -->
            <section class="grid grid-cols-1 gap-8 md:grid-cols-[280px_1fr] md:gap-16">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">{{ $t('usage.section_account') }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ $t('usage.section_account_description', { plan: plan?.name ?? 'Free' }) }}
                    </p>
                </div>

                <div class="divide-y">
                    <UsageMetricRow
                        :label="$t('usage.workspaces')"
                        :current="usage.workspaceCount"
                        :limit="usage.workspaceLimit"
                    />

                    <UsageMetricRow
                        :label="$t('usage.social_accounts')"
                        :current="usage.socialAccountCount"
                        :limit="usage.socialAccountLimit"
                    />

                    <UsageMetricRow
                        :label="$t('usage.members')"
                        :current="usage.memberCount"
                        :limit="usage.memberLimit"
                    />
                </div>
            </section>

            <hr class="my-8 border-border" />

            <!-- AI -->
            <section class="grid grid-cols-1 gap-8 md:grid-cols-[280px_1fr] md:gap-16">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">{{ $t('usage.section_ai') }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ $t('usage.section_ai_description') }}
                    </p>
                </div>

                <div class="divide-y">
                    <UsageMetricRow
                        :label="$t('usage.ai_images')"
                        :current="usage.aiImagesUsed"
                        :limit="usage.aiImagesLimit"
                    />

                    <UsageMetricRow
                        :label="$t('usage.ai_text')"
                        :current="usage.aiTextUsed"
                    />
                </div>
            </section>
        </div>
    </AppLayout>
</template>
