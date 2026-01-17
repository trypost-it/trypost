<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, Plus } from 'lucide-vue-next';
import { computed } from 'vue';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { create as createWorkspaceRoute, switchMethod } from '@/routes/workspaces';

interface Workspace {
    id: string;
    name: string;
}

const page = usePage();
const currentWorkspace = computed<Workspace | null>(() => page.props.currentWorkspace as Workspace | null);
const workspaces = computed<Workspace[]>(() => page.props.workspaces as Workspace[]);

function switchWorkspace(workspace: Workspace) {
    router.post(switchMethod.url(workspace.id), {}, {
        preserveScroll: true,
    });
}

function createWorkspace() {
    router.visit(createWorkspaceRoute.url());
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :as-child="true">
            <Button variant="outline" class="w-full justify-between gap-2 px-3" :class="{ 'text-muted-foreground': !currentWorkspace }">
                <span class="truncate">{{ currentWorkspace?.name || 'Select workspace' }}</span>
                <ChevronsUpDown class="h-4 w-4 shrink-0 opacity-50" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="w-56">
            <DropdownMenuLabel>Workspaces</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                v-for="workspace in workspaces"
                :key="workspace.id"
                class="cursor-pointer"
                @click="switchWorkspace(workspace)"
            >
                <div class="flex w-full items-center justify-between">
                    <span class="truncate">{{ workspace.name }}</span>
                    <Check v-if="currentWorkspace?.id === workspace.id" class="h-4 w-4 shrink-0" />
                </div>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem class="cursor-pointer" @click="createWorkspace">
                <Plus class="mr-2 h-4 w-4" />
                Create workspace
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
