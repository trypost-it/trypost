<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { IconClock, IconDots, IconTrash } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

import WorkspaceController from '@/actions/App/Http/Controllers/App/WorkspaceController';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import PhotoUpload from '@/components/PhotoUpload.vue';
import TimezoneCombobox from '@/components/TimezoneCombobox.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { store as storeInvite, destroy as destroyInvite } from '@/routes/app/invites';
import { remove as removeMemberRoute } from '@/routes/app/members';
import { settings, uploadLogo, deleteLogo } from '@/routes/app/workspace';
import { type BreadcrumbItem } from '@/types';

interface Workspace {
    id: string;
    name: string;
    timezone: string;
    has_logo: boolean;
    logo_url: string | null;
}

interface Member {
    id: string;
    name: string;
    email: string;
    role: string;
    is_owner: boolean;
}

interface Invitation {
    id: string;
    email: string;
    role: string;
}

const props = defineProps<{
    workspace: Workspace;
    members: Member[];
    invitations: Invitation[];
    timezones: Record<string, string>;
}>();

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    {
        title: trans('settings.workspace.title'),
        href: settings().url,
    },
]);

const timezone = ref(props.workspace.timezone);
const inviteDialogOpen = ref(false);
const inviteRole = ref('member');

const removeMemberModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const cancelInvitationModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('settings.workspace.title')" />

        <h1 class="sr-only">{{ $t('settings.workspace.title') }}</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    :title="$t('settings.workspace.logo_heading')"
                    :description="$t('settings.workspace.logo_description')"
                />

                <PhotoUpload
                    :photo-url="workspace.logo_url"
                    :has-photo="workspace.has_logo"
                    :name="workspace.name"
                    :upload-url="uploadLogo().url"
                    :delete-url="deleteLogo().url"
                />
            </div>

            <Separator />

            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    :title="$t('settings.workspace.heading')"
                    :description="$t('settings.workspace.description')"
                />

                <Form
                    v-bind="WorkspaceController.updateSettings.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="name">{{ $t('settings.workspace.name') }}</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="workspace.name"
                            :placeholder="trans('settings.workspace.name_placeholder')"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="timezone">{{ $t('settings.workspace.timezone') }}</Label>
                        <TimezoneCombobox
                            v-model="timezone"
                            :timezones="timezones"
                        />
                        <input type="hidden" name="timezone" :value="timezone" />
                        <InputError :message="errors.timezone" />
                    </div>

                    <Button :disabled="processing">{{ $t('settings.workspace.save') }}</Button>
                </Form>
            </div>

            <Separator />

            <div class="flex flex-col space-y-6">
                <div class="flex items-center justify-between">
                    <HeadingSmall
                        :title="$t('settings.workspace.members_heading')"
                        :description="$t('settings.workspace.members_description')"
                    />

                    <Dialog v-model:open="inviteDialogOpen">
                        <DialogTrigger as-child>
                            <Button variant="secondary">
                                {{ $t('settings.members.invite.submit') }}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>{{ $t('settings.members.invite.title') }}</DialogTitle>
                                <DialogDescription>
                                    {{ $t('settings.members.invite.description') }}
                                </DialogDescription>
                            </DialogHeader>
                            <Form
                                v-bind="storeInvite.form()"
                                class="space-y-4"
                                v-slot="{ errors, processing }"
                                @success="inviteDialogOpen = false; inviteRole = 'member'"
                            >
                                <div class="grid gap-2">
                                    <Label for="invite-email">{{ $t('settings.members.invite.email') }}</Label>
                                    <Input
                                        id="invite-email"
                                        name="email"
                                        type="email"
                                        :placeholder="trans('settings.members.invite.email_placeholder')"
                                    />
                                    <InputError :message="errors.email" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="invite-role">{{ $t('settings.members.invite.role') }}</Label>
                                    <Select v-model="inviteRole" name="role">
                                        <SelectTrigger class="w-full">
                                            <SelectValue :placeholder="trans('settings.members.invite.role_placeholder')" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="member">{{ $t('settings.members.roles.member') }}</SelectItem>
                                            <SelectItem value="admin">{{ $t('settings.members.roles.admin') }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <input type="hidden" name="role" :value="inviteRole" />
                                    <InputError :message="errors.role" />
                                </div>

                                <DialogFooter>
                                    <Button type="submit" :disabled="processing">
                                        {{ $t('settings.members.invite.submit') }}
                                    </Button>
                                    <Button
                                        variant="secondary"
                                        type="button"
                                        @click="inviteDialogOpen = false"
                                    >
                                        {{ $t('settings.members.cancel') }}
                                    </Button>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
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
                                    <Badge :variant="member.is_owner ? 'default' : 'secondary'">
                                        {{ member.role }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <DropdownMenu v-if="!member.is_owner">
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon" class="h-8 w-8">
                                                <IconDots class="size-3.5" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                class="text-destructive"
                                                @click="removeMemberModal?.open({ url: removeMemberRoute.url(member.id) })"
                                            >
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
            </div>

            <ConfirmDeleteModal
                ref="removeMemberModal"
                :title="trans('settings.members.remove_modal.title')"
                :description="trans('settings.members.remove_modal.description')"
                :action="trans('settings.members.remove_modal.action')"
            />

            <ConfirmDeleteModal
                ref="cancelInvitationModal"
                :title="trans('settings.members.cancel_invite_modal.title')"
                :description="trans('settings.members.cancel_invite_modal.description')"
                :action="trans('settings.members.cancel_invite_modal.action')"
            />
        </SettingsLayout>
    </AppLayout>
</template>
