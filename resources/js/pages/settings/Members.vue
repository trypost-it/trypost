<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { UserPlus, Users, Mail, Trash2, Crown, User, Shield } from 'lucide-vue-next';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { members as membersRoute } from '@/routes';
import { destroy as destroyInvite, store as storeInvite } from '@/routes/invites';
import { remove as removeMember } from '@/routes/members';
import { type BreadcrumbItem } from '@/types';

interface Workspace {
    id: string;
    name: string;
}

interface Member {
    id: string;
    name: string;
    email: string;
    role: string;
}

interface Invite {
    id: string;
    email: string;
    role: string;
    status: string;
    inviter: { name: string };
}

interface Role {
    value: string;
    label: string;
}

interface Props {
    workspace: Workspace;
    owner: Member;
    members: Member[];
    invites: Invite[];
    roles: Role[];
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Members', href: membersRoute.url() },
];

const form = useForm({
    email: '',
    role: 'member',
});

function submitInvite() {
    form.post(storeInvite.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
}

function cancelInvite(inviteId: string) {
    if (confirm('Are you sure you want to cancel this invite?')) {
        router.delete(destroyInvite.url(inviteId), {
            preserveScroll: true,
        });
    }
}

function handleRemoveMember(memberId: string) {
    if (confirm('Are you sure you want to remove this member?')) {
        router.delete(removeMember.url(memberId), {
            preserveScroll: true,
        });
    }
}

function getStatusLabel(status: string): string {
    const labels: Record<string, string> = {
        pending: 'Pending',
        accepted: 'Accepted',
    };
    return labels[status] || status;
}

function getStatusColor(status: string): string {
    const colors: Record<string, string> = {
        pending: 'bg-yellow-100 text-yellow-800',
        accepted: 'bg-green-100 text-green-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
}

function getRoleLabel(role: string): string {
    const labels: Record<string, string> = {
        owner: 'Owner',
        admin: 'Admin',
        member: 'Member',
    };
    return labels[role] || role;
}

function getRoleIcon(role: string) {
    if (role === 'admin') return Shield;
    return User;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Members" />

        <h1 class="sr-only">Members</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="Team members"
                    description="Manage members and invites for this workspace"
                />

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <UserPlus class="h-5 w-5" />
                            Invite Member
                        </CardTitle>
                        <CardDescription>
                            Send an email invite to add collaborators
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitInvite" class="space-y-4">
                            <div class="space-y-2">
                                <Label for="email">Email</Label>
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    placeholder="collaborator@email.com"
                                    :class="{ 'border-red-500': form.errors.email }"
                                />
                                <p v-if="form.errors.email" class="text-sm text-red-500">
                                    {{ form.errors.email }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="role">Role</Label>
                                <Select v-model="form.role">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="role in roles"
                                            :key="role.value"
                                            :value="role.value"
                                        >
                                            {{ role.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <Button type="submit" :disabled="form.processing" class="w-full">
                                <Mail class="mr-2 h-4 w-4" />
                                Send Invite
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Mail class="h-5 w-5" />
                            Pending Invites
                        </CardTitle>
                        <CardDescription>
                            Invites awaiting acceptance
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="invites.length === 0" class="text-center py-6 text-muted-foreground">
                            No pending invites
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="invite in invites"
                                :key="invite.id"
                                class="flex items-center justify-between p-3 rounded-lg border"
                            >
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium truncate">{{ invite.email }}</p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <Badge variant="outline" class="text-xs">
                                            {{ getRoleLabel(invite.role) }}
                                        </Badge>
                                        <Badge :class="getStatusColor(invite.status)" class="text-xs">
                                            {{ getStatusLabel(invite.status) }}
                                        </Badge>
                                    </div>
                                </div>
                                <Button
                                    v-if="invite.status === 'pending'"
                                    variant="ghost"
                                    size="icon"
                                    @click="cancelInvite(invite.id)"
                                >
                                    <Trash2 class="h-4 w-4 text-red-500" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Users class="h-5 w-5" />
                            Members
                        </CardTitle>
                        <CardDescription>
                            People with access to this workspace
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between p-3 rounded-lg border bg-muted/50">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                        <Crown class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="font-medium">{{ owner.name }}</p>
                                        <p class="text-sm text-muted-foreground">{{ owner.email }}</p>
                                    </div>
                                </div>
                                <Badge>Owner</Badge>
                            </div>

                            <div
                                v-for="member in members"
                                :key="member.id"
                                class="flex items-center justify-between p-3 rounded-lg border"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-secondary text-secondary-foreground">
                                        <component :is="getRoleIcon(member.role)" class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="font-medium">{{ member.name }}</p>
                                        <p class="text-sm text-muted-foreground">{{ member.email }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge variant="outline" class="capitalize">{{ member.role }}</Badge>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="handleRemoveMember(member.id)"
                                    >
                                        <Trash2 class="h-4 w-4 text-red-500" />
                                    </Button>
                                </div>
                            </div>

                            <div v-if="members.length === 0" class="text-center py-6 text-muted-foreground">
                                No members besides the owner
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
