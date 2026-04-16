<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { IconClock, IconDots, IconShield, IconTrash, IconUser } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

import WorkspaceController from '@/actions/App/Http/Controllers/App/WorkspaceController';
import { WorkspaceRole } from '@/enums/workspace-role';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import InviteMemberDialog from '@/components/members/InviteMemberDialog.vue';
import PhotoUpload from '@/components/PhotoUpload.vue';
import TimezoneCombobox from '@/components/TimezoneCombobox.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { destroy as destroyInvite } from '@/routes/app/invites';
import { remove as removeMemberRoute, updateRole } from '@/routes/app/members';
import { uploadLogo, deleteLogo } from '@/routes/app/workspace';
interface Workspace {
    id: string;
    name: string;
    timezone: string;
    has_logo: boolean;
    logo_url: string | null;
    brand_website: string | null;
    brand_description: string | null;
    brand_tone: string;
    brand_voice_notes: string | null;
    content_language: string;
}

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

const props = defineProps<{
    workspace: Workspace;
    members: Member[];
    invitations: Invitation[];
    timezones: Record<string, string>;
}>();

const timezone = ref(props.workspace.timezone);
const brandTone = ref(props.workspace.brand_tone ?? 'professional');
const contentLanguage = ref(props.workspace.content_language ?? 'en');
const inviteDialogOpen = ref(false);

const removeMemberModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const cancelInvitationModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

const changeRole = (member: Member, role: string) => {
    router.put(updateRole.url(member.id), { role });
};
</script>

<template>
    <AppLayout :title="$t('settings.workspace.title')">
        <Head :title="$t('settings.workspace.title')" />

        <h1 class="sr-only">{{ $t('settings.workspace.title') }}</h1>

        <div class="mx-auto max-w-4xl px-4 py-6 space-y-12">
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
                <HeadingSmall
                    :title="$t('settings.brand.title')"
                    :description="$t('settings.brand.description')"
                />

                <Form
                    v-bind="WorkspaceController.updateSettings.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <input type="hidden" name="name" :value="workspace.name" />
                    <input type="hidden" name="timezone" :value="workspace.timezone" />

                    <div class="grid gap-2">
                        <Label for="brand_website">{{ $t('settings.brand.website') }}</Label>
                        <Input
                            id="brand_website"
                            name="brand_website"
                            type="url"
                            :default-value="workspace.brand_website ?? ''"
                            :placeholder="trans('settings.brand.website_placeholder')"
                        />
                        <InputError :message="errors.brand_website" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="brand_description">{{ $t('settings.brand.brand_description') }}</Label>
                        <Textarea
                            id="brand_description"
                            name="brand_description"
                            :default-value="workspace.brand_description ?? ''"
                            :placeholder="trans('settings.brand.brand_description_placeholder')"
                            rows="3"
                        />
                        <InputError :message="errors.brand_description" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="brand_tone">{{ $t('settings.brand.tone') }}</Label>
                            <Select v-model="brandTone" name="brand_tone">
                                <SelectTrigger id="brand_tone" class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="professional">{{ $t('settings.brand.tone_professional') }}</SelectItem>
                                    <SelectItem value="casual">{{ $t('settings.brand.tone_casual') }}</SelectItem>
                                    <SelectItem value="friendly">{{ $t('settings.brand.tone_friendly') }}</SelectItem>
                                    <SelectItem value="bold">{{ $t('settings.brand.tone_bold') }}</SelectItem>
                                    <SelectItem value="inspirational">{{ $t('settings.brand.tone_inspirational') }}</SelectItem>
                                    <SelectItem value="humorous">{{ $t('settings.brand.tone_humorous') }}</SelectItem>
                                    <SelectItem value="educational">{{ $t('settings.brand.tone_educational') }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <input type="hidden" name="brand_tone" :value="brandTone" />
                            <InputError :message="errors.brand_tone" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="content_language">{{ $t('settings.brand.content_language') }}</Label>
                            <Select v-model="contentLanguage" name="content_language">
                                <SelectTrigger id="content_language" class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="en">English</SelectItem>
                                    <SelectItem value="pt-BR">Português (Brasil)</SelectItem>
                                    <SelectItem value="es">Español</SelectItem>
                                </SelectContent>
                            </Select>
                            <input type="hidden" name="content_language" :value="contentLanguage" />
                            <InputError :message="errors.content_language" />
                        </div>
                    </div>

                    <p class="-mt-4 text-xs text-muted-foreground">
                        {{ $t('settings.brand.content_language_description') }}
                    </p>

                    <div class="grid gap-2">
                        <Label for="brand_voice_notes">{{ $t('settings.brand.voice_notes') }}</Label>
                        <Textarea
                            id="brand_voice_notes"
                            name="brand_voice_notes"
                            :default-value="workspace.brand_voice_notes ?? ''"
                            :placeholder="trans('settings.brand.voice_notes_placeholder')"
                            rows="3"
                        />
                        <InputError :message="errors.brand_voice_notes" />
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
            </div>

            <InviteMemberDialog v-model:open="inviteDialogOpen" />

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
        </div>
    </AppLayout>
</template>
