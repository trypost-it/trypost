<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    IconClock,
    IconDots,
    IconShield,
    IconTrash,
    IconUser,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
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
import { useFeatureAccess } from '@/composables/useFeatureAccess';
import { useUpgradeDialog } from '@/composables/useUpgradeDialog';
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
const removeMemberModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(
    null,
);
const cancelInvitationModal = ref<InstanceType<
    typeof ConfirmDeleteModal
> | null>(null);

const { canInviteMember } = useFeatureAccess();
const { openUpgrade } = useUpgradeDialog();

const handleInviteClick = () => {
    if (!canInviteMember.value) {
        openUpgrade(trans('billing.upgrade_dialog.reasons.member_limit'));
        return;
    }
    inviteDialogOpen.value = true;
};

const changeRole = (member: Member, role: string) => {
    router.put(updateRole.url(member.id), { role });
};
</script>

<template>
    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between gap-4">
            <HeadingSmall
                :title="$t('settings.workspace.members_heading')"
                :description="$t('settings.workspace.members_description')"
            />

            <Button @click="handleInviteClick">
                {{ $t('settings.members.invite.submit') }}
            </Button>
        </div>

        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>{{ $t('settings.workspace.name') }}</TableHead>
                    <TableHead>{{
                        $t('settings.members.invite.email')
                    }}</TableHead>
                    <TableHead>{{
                        $t('settings.members.invite.role')
                    }}</TableHead>
                    <TableHead class="w-10" />
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="member in members" :key="member.id">
                    <TableCell>{{ member.name }}</TableCell>
                    <TableCell>{{ member.email }}</TableCell>
                    <TableCell>
                        <Badge
                            :variant="
                                member.role === WorkspaceRole.Admin
                                    ? 'default'
                                    : 'secondary'
                            "
                        >
                            {{ member.role }}
                        </Badge>
                    </TableCell>
                    <TableCell>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    class="size-8"
                                >
                                    <IconDots class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    v-if="member.role === WorkspaceRole.Member"
                                    @click="
                                        changeRole(member, WorkspaceRole.Admin)
                                    "
                                >
                                    <IconShield class="size-4" />
                                    {{ $t('settings.members.make_admin') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="member.role === WorkspaceRole.Admin"
                                    @click="
                                        changeRole(member, WorkspaceRole.Member)
                                    "
                                >
                                    <IconUser class="size-4" />
                                    {{ $t('settings.members.make_member') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    variant="destructive"
                                    @click="
                                        removeMemberModal?.open({
                                            url: removeMemberRoute.url(
                                                member.id,
                                            ),
                                        })
                                    "
                                >
                                    <IconTrash class="size-4" />
                                    {{ $t('settings.members.remove') }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </TableCell>
                </TableRow>
                <TableRow
                    v-for="invitation in invitations"
                    :key="`inv-${invitation.id}`"
                >
                    <TableCell class="text-foreground/60">
                        <div class="flex items-center gap-2">
                            <IconClock class="size-4" />
                            <span class="italic">{{
                                $t('settings.members.pending.title')
                            }}</span>
                        </div>
                    </TableCell>
                    <TableCell class="text-foreground/60">
                        {{ invitation.email }}
                    </TableCell>
                    <TableCell>
                        <Badge variant="outline">
                            {{ invitation.role }}
                        </Badge>
                    </TableCell>
                    <TableCell>
                        <Button
                            variant="outline"
                            size="icon"
                            class="size-8 bg-rose-100 hover:bg-rose-200"
                            :aria-label="
                                $t(
                                    'settings.members.cancel_invite_modal.action',
                                )
                            "
                            @click="
                                cancelInvitationModal?.open({
                                    url: destroyInvite.url(invitation.id),
                                })
                            "
                        >
                            <IconTrash class="size-4 text-rose-700" />
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

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
            :description="
                $t('settings.members.cancel_invite_modal.description')
            "
            :action="$t('settings.members.cancel_invite_modal.action')"
        />
    </div>
</template>
