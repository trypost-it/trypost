<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { UserPlus, Users, Mail, Clock, Trash2, Crown, User } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { type BreadcrumbItemType } from '@/types';

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
    role: { value: string; label: string };
    status: { value: string; label: string; color: string };
    expires_at: string;
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

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    {
        title: 'Workspaces',
        href: '/workspaces',
    },
    {
        title: props.workspace.name,
        href: `/workspaces/${props.workspace.id}`,
    },
    {
        title: 'Equipe',
        href: `/workspaces/${props.workspace.id}/members`,
    },
];

const form = useForm({
    email: '',
    role: 'member',
});

function submitInvite() {
    form.post(`/workspaces/${props.workspace.id}/invites`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
}

function cancelInvite(inviteId: string) {
    if (confirm('Tem certeza que deseja cancelar este convite?')) {
        router.delete(`/workspaces/${props.workspace.id}/invites/${inviteId}`, {
            preserveScroll: true,
        });
    }
}

function removeMember(memberId: string) {
    if (confirm('Tem certeza que deseja remover este membro?')) {
        router.delete(`/workspaces/${props.workspace.id}/members/${memberId}`, {
            preserveScroll: true,
        });
    }
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function getStatusColor(color: string): string {
    const colors: Record<string, string> = {
        yellow: 'bg-yellow-100 text-yellow-800',
        green: 'bg-green-100 text-green-800',
        gray: 'bg-gray-100 text-gray-800',
        red: 'bg-red-100 text-red-800',
    };
    return colors[color] || 'bg-gray-100 text-gray-800';
}
</script>

<template>
    <Head :title="`Equipe - ${workspace.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Equipe</h1>
                    <p class="text-muted-foreground">
                        Gerencie os membros e convites do workspace
                    </p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <UserPlus class="h-5 w-5" />
                            Convidar Membro
                        </CardTitle>
                        <CardDescription>
                            Envie um convite por email para adicionar colaboradores
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
                                    placeholder="colaborador@email.com"
                                    :class="{ 'border-red-500': form.errors.email }"
                                />
                                <p v-if="form.errors.email" class="text-sm text-red-500">
                                    {{ form.errors.email }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="role">Papel</Label>
                                <Select v-model="form.role">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecione um papel" />
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
                                Enviar Convite
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Clock class="h-5 w-5" />
                            Convites Pendentes
                        </CardTitle>
                        <CardDescription>
                            Convites aguardando aceitação
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="invites.length === 0" class="text-center py-6 text-muted-foreground">
                            Nenhum convite pendente
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
                                            {{ invite.role.label }}
                                        </Badge>
                                        <Badge :class="getStatusColor(invite.status.color)" class="text-xs">
                                            {{ invite.status.label }}
                                        </Badge>
                                    </div>
                                    <p class="text-xs text-muted-foreground mt-1">
                                        Expira em {{ formatDate(invite.expires_at) }}
                                    </p>
                                </div>
                                <Button
                                    v-if="invite.status.value === 'pending'"
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
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Users class="h-5 w-5" />
                        Membros
                    </CardTitle>
                    <CardDescription>
                        Pessoas com acesso a este workspace
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
                            <Badge>Proprietário</Badge>
                        </div>

                        <div
                            v-for="member in members"
                            :key="member.id"
                            class="flex items-center justify-between p-3 rounded-lg border"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-secondary text-secondary-foreground">
                                    <User class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="font-medium">{{ member.name }}</p>
                                    <p class="text-sm text-muted-foreground">{{ member.email }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge variant="outline">{{ member.role }}</Badge>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    @click="removeMember(member.id)"
                                >
                                    <Trash2 class="h-4 w-4 text-red-500" />
                                </Button>
                            </div>
                        </div>

                        <div v-if="members.length === 0" class="text-center py-6 text-muted-foreground">
                            Nenhum membro além do proprietário
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
