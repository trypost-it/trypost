<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index as hashtagsIndex, update as hashtagsUpdate } from '@/routes/hashtags';
import { type BreadcrumbItemType } from '@/types';

interface Workspace {
    id: string;
    name: string;
}

interface Hashtag {
    id: string;
    name: string;
    hashtags: string;
}

interface Props {
    workspace: Workspace;
    hashtag: Hashtag;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Hashtags', href: hashtagsIndex.url() },
    { title: 'Edit', href: '#' },
];

const form = useForm({
    name: props.hashtag.name,
    hashtags: props.hashtag.hashtags,
});

const submit = () => {
    form.put(hashtagsUpdate.url(props.hashtag.id));
};
</script>

<template>
    <Head title="Edit Hashtag Group" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Edit Hashtag Group</h1>
                <p class="text-muted-foreground">
                    Update your hashtag group details
                </p>
            </div>

            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>Hashtag Group Details</CardTitle>
                    <CardDescription>
                        Update the name and hashtags for this group
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Group Name</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="e.g. Marketing, Travel, Food"
                                :class="{ 'border-destructive': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="text-sm text-destructive">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="hashtags">Hashtags</Label>
                            <Textarea
                                id="hashtags"
                                v-model="form.hashtags"
                                placeholder="#marketing #socialmedia #business #growth"
                                rows="6"
                                :class="{ 'border-destructive': form.errors.hashtags }"
                            />
                            <p class="text-sm text-muted-foreground">
                                Enter hashtags separated by spaces or commas. Include the # symbol.
                            </p>
                            <p v-if="form.errors.hashtags" class="text-sm text-destructive">
                                {{ form.errors.hashtags }}
                            </p>
                        </div>

                        <div class="flex gap-2">
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
