<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Settings } from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import TimezoneCombobox from '@/components/TimezoneCombobox.vue';
import { type BreadcrumbItemType } from '@/types';

interface Workspace {
    id: string;
    name: string;
    timezone: string;
}

interface Props {
    workspace: Workspace;
    timezones: Record<string, string>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Settings', href: '/settings' },
];

const form = useForm({
    name: props.workspace.name,
    timezone: props.workspace.timezone,
});

const submit = () => {
    form.put('/settings');
};
</script>

<template>
    <Head title="Settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Settings</h1>
                <p class="text-muted-foreground">
                    Manage your workspace settings
                </p>
            </div>

            <Card class="max-w-lg">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Settings class="h-5 w-5" />
                        Workspace Settings
                    </CardTitle>
                    <CardDescription>
                        Update your workspace name and timezone
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Name</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="My Workspace"
                                :class="{ 'border-destructive': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="text-sm text-destructive">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="timezone">Timezone</Label>
                            <TimezoneCombobox
                                v-model="form.timezone"
                                :timezones="timezones"
                            />
                            <p v-if="form.errors.timezone" class="text-sm text-destructive">
                                {{ form.errors.timezone }}
                            </p>
                        </div>

                        <div class="flex gap-2 pt-2">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Saving...' : 'Save Changes' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
