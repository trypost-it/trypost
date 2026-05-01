<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { IconClock, IconDots, IconShield, IconTrash, IconUser } from '@tabler/icons-vue';
import { ref } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InviteMemberDialog from '@/components/members/InviteMemberDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { WorkspaceRole } from '@/enums/workspace-role';
import { destroy as destroyInvite } from '@/routes/app/invites';
import { remove as removeMemberRoute, updateRole } from '@/routes/app/members';

interface Member {
    id: string;
    name: string;
    email: string;
    role: string;
}

interface Invitation {
    id: string;
    email: string;
    role: string;
}

defineProps<{
    members: Member[];
    invitations: Invitation[];
}>();

const inviteDialogOpen = ref(false);
const removeMemberModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const cancelInvitationModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

const changeRole = (member: Member, role: string) => {
    router.put(updateRole.url(member.id), { role });
};
</script>

<template>
    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <HeadingSmall
                :title="$t('settings.workspace.members_heading')"
                :description="$t('settings.workspace.members_description')"
            />

            <Button variant="secondary" @click="inviteDialogOpen = true">
                {{ $t('settings.members.invite.submit') }}
            </Button>
        </div>

        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ $t('settings.workspace.name') }}</TableHead>
                        <TableHead>{{ $t('settings.members.invite.email') }}</TableHead>
                        <TableHead>{{ $t('settings.members.invite.role') }}</TableHead>
                        <TableHead class="w-10" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="member in members" :key="member.id">
                        <TableCell class="font-medium">
                            {{ member.name }}
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ member.email }}
                        </TableCell>
                        <TableCell>
                            <Badge :variant="member.role === WorkspaceRole.Admin ? 'default' : 'secondary'">
                                {{ member.role }}
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon" class="h-8 w-8">
                                        <IconDots class="size-3.5" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        v-if="member.role === WorkspaceRole.Member"
                                        @click="changeRole(member, WorkspaceRole.Admin)"
                                    >
                                        <IconShield class="size-3.5" />
                                        {{ $t('settings.members.make_admin') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="member.role === WorkspaceRole.Admin"
                                        @click="changeRole(member, WorkspaceRole.Member)"
                                    >
                                        <IconUser class="size-3.5" />
                                        {{ $t('settings.members.make_member') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        variant="destructive"
                                        @click="removeMemberModal?.open({ url: removeMemberRoute.url(member.id) })"
                                    >
                                        <IconTrash class="size-3.5" />
                                        {{ $t('settings.members.remove') }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                    <TableRow
                        v-for="invitation in invitations"
                        :key="`inv-${invitation.id}`"
                        class="text-muted-foreground"
                    >
                        <TableCell>
                            <div class="flex items-center gap-2">
                                <IconClock class="size-3.5" />
                                <span class="italic">{{ $t('settings.members.pending.title') }}</span>
                            </div>
                        </TableCell>
                        <TableCell>
                            {{ invitation.email }}
                        </TableCell>
                        <TableCell>
                            <Badge variant="outline">
                                {{ invitation.role }}
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8 text-destructive"
                                @click="cancelInvitationModal?.open({ url: destroyInvite.url(invitation.id) })"
                            >
                                <IconTrash class="size-3.5" />
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <InviteMemberDialog v-model:open="inviteDialogOpen" />

        <ConfirmDeleteModal
            ref="removeMemberModal"
            :title="$t('settings.members.remove_modal.title')"
            :description="$t('settings.members.remove_modal.description')"
            :action="$t('settings.members.remove_modal.action')"
        />

        <ConfirmDeleteModal
            ref="cancelInvitationModal"
            :title="$t('settings.members.cancel_invite_modal.title')"
            :description="$t('settings.members.cancel_invite_modal.description')"
            :action="$t('settings.members.cancel_invite_modal.action')"
        />
    </div>
</template>
