<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItemType } from '@/types';

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Create Workspace', href: '/workspaces/create' },
];

const form = useForm({
    name: '',
});

const submit = () => {
    form.post('/workspaces');
};
</script>

<template>
    <Head title="Criar Workspace" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Criar Workspace</h1>
                <p class="text-muted-foreground">
                    Crie um novo workspace para organizar suas redes sociais
                </p>
            </div>

            <Card class="max-w-lg">
                <CardHeader>
                    <CardTitle>Informações do Workspace</CardTitle>
                    <CardDescription>
                        Dê um nome para identificar seu workspace
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Nome</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="Meu Workspace"
                                :class="{ 'border-destructive': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="text-sm text-destructive">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="flex gap-2">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Criando...' : 'Criar Workspace' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
