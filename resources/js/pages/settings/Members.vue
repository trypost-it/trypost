<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { IconUserPlus, IconUsers, IconMail, IconTrash, IconCrown, IconUser, IconShield } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

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

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    { title: trans('settings.members.title'), href: membersRoute.url() },
]);

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
    if (confirm(trans('settings.members.invite.cancel_confirm'))) {
        router.delete(destroyInvite.url(inviteId), {
            preserveScroll: true,
        });
    }
}

function handleRemoveMember(memberId: string) {
    if (confirm(trans('settings.members.list.remove_confirm'))) {
        router.delete(removeMember.url(memberId), {
            preserveScroll: true,
        });
    }
}

function getRoleLabel(role: string): string {
    return trans(`settings.members.roles.${role}`);
}

function getRoleIcon(role: string) {
    if (role === 'admin') return IconShield;
    return IconUser;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('settings.members.title')" />

        <h1 class="sr-only">{{ $t('settings.members.title') }}</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    :title="$t('settings.members.heading')"
                    :description="$t('settings.members.description')"
                />

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <IconUserPlus class="h-5 w-5" />
                            {{ $t('settings.members.invite.title') }}
                        </CardTitle>
                        <CardDescription>
                            {{ $t('settings.members.invite.description') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitInvite" class="space-y-4">
                            <div class="space-y-2">
                                <Label for="email">{{ $t('settings.members.invite.email') }}</Label>
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    :placeholder="trans('settings.members.invite.email_placeholder')"
                                    :class="{ 'border-red-500': form.errors.email }"
                                />
                                <p v-if="form.errors.email" class="text-sm text-red-500">
                                    {{ form.errors.email }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="role">{{ $t('settings.members.invite.role') }}</Label>
                                <Select v-model="form.role">
                                    <SelectTrigger>
                                        <SelectValue :placeholder="trans('settings.members.invite.role_placeholder')" />
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
                                <IconMail class="mr-2 h-4 w-4" />
                                {{ $t('settings.members.invite.submit') }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <IconMail class="h-5 w-5" />
                            {{ $t('settings.members.pending.title') }}
                        </CardTitle>
                        <CardDescription>
                            {{ $t('settings.members.pending.description') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="invites.length === 0" class="text-center py-6 text-muted-foreground">
                            {{ $t('settings.members.pending.empty') }}
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="invite in invites"
                                :key="invite.id"
                                class="flex items-center justify-between p-3 rounded-lg border"
                            >
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium truncate">{{ invite.email }}</p>
                                    <Badge variant="outline" class="text-xs mt-1">
                                        {{ getRoleLabel(invite.role) }}
                                    </Badge>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    @click="cancelInvite(invite.id)"
                                >
                                    <IconTrash class="h-4 w-4 text-red-500" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <IconUsers class="h-5 w-5" />
                            {{ $t('settings.members.list.title') }}
                        </CardTitle>
                        <CardDescription>
                            {{ $t('settings.members.list.description') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between p-3 rounded-lg border bg-muted/50">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                        <IconCrown class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="font-medium">{{ owner.name }}</p>
                                        <p class="text-sm text-muted-foreground">{{ owner.email }}</p>
                                    </div>
                                </div>
                                <Badge>{{ $t('settings.members.roles.owner') }}</Badge>
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
                                    <Badge variant="outline">{{ getRoleLabel(member.role) }}</Badge>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="handleRemoveMember(member.id)"
                                    >
                                        <IconTrash class="h-4 w-4 text-red-500" />
                                    </Button>
                                </div>
                            </div>

                            <div v-if="members.length === 0" class="text-center py-6 text-muted-foreground">
                                {{ $t('settings.members.list.empty') }}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
