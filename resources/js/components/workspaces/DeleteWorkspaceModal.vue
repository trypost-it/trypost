<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { IconAlertTriangle, IconTrash } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { deletionImpact as deletionImpactRoute, destroy as destroyRoute } from '@/routes/app/workspaces';

interface DeletionImpact {
    posts: number;
    social_accounts: number;
    labels: number;
    signatures: number;
    members: number;
}

interface WorkspaceRef {
    id: string;
    name: string;
}

const isOpen = ref(false);
const processing = ref(false);
const workspaceId = ref<string | null>(null);
const workspaceName = ref<string | null>(null);
const impact = ref<DeletionImpact | null>(null);
const loadingImpact = ref(false);

const impactHttp = useHttp<Record<string, never>, DeletionImpact>({});

const open = (workspace: WorkspaceRef) => {
    workspaceId.value = workspace.id;
    workspaceName.value = workspace.name;
    impact.value = null;
    loadingImpact.value = true;
    isOpen.value = true;

    impactHttp.get(deletionImpactRoute.url(workspace.id), {
        onSuccess: (data) => {
            impact.value = data as DeletionImpact;
            loadingImpact.value = false;
        },
        onError: () => {
            loadingImpact.value = false;
        },
    });
};

const close = () => {
    isOpen.value = false;
    processing.value = false;
};

const onOpenChange = (value: boolean) => {
    isOpen.value = value;
    if (!value) {
        close();
    }
};

const confirmDelete = () => {
    if (!workspaceId.value || processing.value) return;

    processing.value = true;

    router.delete(destroyRoute.url(workspaceId.value), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            close();
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};

defineExpose({ open, close });
</script>

<template>
    <Dialog :open="isOpen" @update:open="onOpenChange">
        <DialogContent :show-close-button="false" class="sm:max-w-md">
            <DialogHeader class="items-start text-left">
                <div class="flex items-start gap-3">
                    <div
                        class="inline-flex size-12 -rotate-3 shrink-0 items-center justify-center rounded-2xl border-2 border-foreground bg-rose-200 shadow-2xs"
                    >
                        <IconAlertTriangle class="size-6 text-rose-700" stroke-width="2.25" />
                    </div>
                    <div class="flex-1 space-y-1">
                        <DialogTitle>{{ $t('workspaces.delete.title') }}</DialogTitle>
                        <DialogDescription class="space-y-1">
                            <span class="block">{{ $t('workspaces.delete.description') }}</span>
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div class="space-y-3">
                <p class="text-sm font-medium text-foreground">
                    {{ $t('workspaces.delete.impact_title') }}
                </p>

                <div v-if="loadingImpact" class="space-y-2">
                    <div v-for="i in 3" :key="i" class="h-4 animate-pulse rounded bg-muted" />
                </div>

                <ul v-else-if="impact" class="space-y-1.5 text-sm text-foreground/80">
                    <li v-if="impact.posts > 0" class="flex items-center gap-2">
                        <IconTrash class="size-3.5 shrink-0 text-rose-600" />
                        {{ $t('workspaces.delete.impact.posts', { count: impact.posts }, impact.posts) }}
                    </li>
                    <li v-if="impact.social_accounts > 0" class="flex items-center gap-2">
                        <IconTrash class="size-3.5 shrink-0 text-rose-600" />
                        {{ $t('workspaces.delete.impact.social_accounts', { count: impact.social_accounts }, impact.social_accounts) }}
                    </li>
                    <li v-if="impact.labels > 0" class="flex items-center gap-2">
                        <IconTrash class="size-3.5 shrink-0 text-rose-600" />
                        {{ $t('workspaces.delete.impact.labels', { count: impact.labels }, impact.labels) }}
                    </li>
                    <li v-if="impact.signatures > 0" class="flex items-center gap-2">
                        <IconTrash class="size-3.5 shrink-0 text-rose-600" />
                        {{ $t('workspaces.delete.impact.signatures', { count: impact.signatures }, impact.signatures) }}
                    </li>
                    <li v-if="impact.members > 0" class="flex items-center gap-2">
                        <IconTrash class="size-3.5 shrink-0 text-rose-600" />
                        {{ $t('workspaces.delete.impact.members', { count: impact.members }, impact.members) }}
                    </li>
                </ul>

                <p class="text-sm font-semibold text-rose-700">
                    {{ $t('common.confirm_modal.cannot_be_undone') }}
                </p>
            </div>

            <DialogFooter class="sm:justify-start sm:gap-2">
                <Button
                    variant="destructive"
                    :disabled="processing || loadingImpact"
                    @click="confirmDelete"
                >
                    {{ $t('workspaces.delete.confirm') }}
                </Button>
                <Button variant="outline" @click="close">
                    {{ trans('common.cancel') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
