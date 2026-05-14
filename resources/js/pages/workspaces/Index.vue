<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { IconDots, IconTrash } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

import DeleteWorkspaceModal from '@/components/workspaces/DeleteWorkspaceModal.vue';
import { Avatar } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { create as createWorkspace, switchMethod } from '@/routes/app/workspaces';

interface Workspace {
    id: string;
    name: string;
    logo_url: string | null;
    social_accounts_count: number;
    posts_count: number;
}

interface Props {
    workspaces: Workspace[];
    currentWorkspaceId: string | null;
}

defineProps<Props>();

const deleteModal = ref<InstanceType<typeof DeleteWorkspaceModal> | null>(null);

const switchToWorkspace = (workspace: Workspace) => {
    router.post(switchMethod.url(workspace.id), {}, {
        preserveState: false,
    });
};
</script>

<template>
    <Head :title="$t('workspaces.title')" />

    <AuthLayout
        :title="$t('workspaces.select_title')"
        :description="$t('workspaces.select_description')"
    >
        <div class="space-y-3">
            <div
                v-for="workspace in workspaces"
                :key="workspace.id"
                class="flex cursor-pointer items-center gap-3 rounded-lg border p-4 transition-colors hover:bg-accent/50"
                :class="workspace.id === currentWorkspaceId ? 'border-primary/50 bg-accent/30' : ''"
                @click="switchToWorkspace(workspace)"
            >
                <Avatar
                    :src="workspace.logo_url"
                    :name="workspace.name"
                    class="size-10 shrink-0 rounded-lg"
                    fallback-class="bg-muted text-muted-foreground"
                />
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ workspace.name }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ trans('workspaces.connections', { count: String(workspace.social_accounts_count) }) }} · {{ trans('workspaces.posts', { count: String(workspace.posts_count) }) }}
                    </p>
                </div>
                <Badge v-if="workspace.id === currentWorkspaceId" variant="secondary" class="shrink-0">
                    {{ $t('workspaces.current') }}
                </Badge>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child @click.stop>
                        <Button variant="ghost" size="icon" class="size-8 shrink-0">
                            <IconDots class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" @click.stop>
                        <DropdownMenuItem
                            variant="destructive"
                            @click="deleteModal?.open(workspace)"
                        >
                            <IconTrash class="size-4" />
                            {{ $t('workspaces.delete.confirm') }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <Link :href="createWorkspace.url()">
            <Button variant="outline" class="w-full">
                {{ $t('workspaces.create.submit') }}
            </Button>
        </Link>

        <DeleteWorkspaceModal ref="deleteModal" />
    </AuthLayout>
</template>
