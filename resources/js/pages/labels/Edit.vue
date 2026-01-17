<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as labelsIndex, update as labelsUpdate } from '@/routes/labels';
import { type BreadcrumbItemType } from '@/types';

interface Workspace {
    id: string;
    name: string;
}

interface LabelType {
    id: string;
    name: string;
    color: string;
}

interface Props {
    workspace: Workspace;
    label: LabelType;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Labels', href: labelsIndex.url() },
    { title: 'Edit', href: '#' },
];

const colors = [
    '#FDFD96',
    '#FFD580',
    '#FFB3BA',
    '#FF69B4',
    '#DDA0DD',
    '#89CFF0',
    '#90EE90',
    '#D2B48C',
    '#D3D3D3',
];

const form = useForm({
    name: props.label.name,
    color: props.label.color,
});

const submit = () => {
    form.put(labelsUpdate.url(props.label.id));
};
</script>

<template>
    <Head title="Edit Label" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Edit Label</h1>
                <p class="text-muted-foreground">
                    Update your label details
                </p>
            </div>

            <Card class="max-w-md">
                <CardHeader>
                    <CardTitle>Label Details</CardTitle>
                    <CardDescription>
                        Update the name and color for this label
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Name</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="Enter label name..."
                                :class="{ 'border-destructive': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="text-sm text-destructive">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label>Color</Label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="color in colors"
                                    :key="color"
                                    type="button"
                                    class="h-10 w-10 rounded-lg transition-all"
                                    :class="[
                                        form.color === color
                                            ? 'ring-2 ring-primary ring-offset-2'
                                            : 'hover:scale-110'
                                    ]"
                                    :style="{ backgroundColor: color }"
                                    @click="form.color = color"
                                />
                            </div>
                            <p v-if="form.errors.color" class="text-sm text-destructive">
                                {{ form.errors.color }}
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
