<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store as hashtagsStore } from '@/routes/hashtags';

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    name: '',
    hashtags: '',
});

const submit = () => {
    form.post(hashtagsStore.url(), {
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
};

const handleOpenChange = (value: boolean) => {
    if (value) {
        form.reset();
        form.clearErrors();
    }
    open.value = value;
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Create Hashtag Group</DialogTitle>
                <DialogDescription>
                    Give your group a name and add hashtags separated by spaces or commas
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="create-name">Group Name</Label>
                    <Input
                        id="create-name"
                        v-model="form.name"
                        placeholder="e.g. Marketing, Travel, Food"
                        :class="{ 'border-destructive': form.errors.name }"
                    />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="create-hashtags">Hashtags</Label>
                    <Textarea
                        id="create-hashtags"
                        v-model="form.hashtags"
                        placeholder="#marketing #socialmedia #business #growth"
                        rows="4"
                        :class="{ 'border-destructive': form.errors.hashtags }"
                    />
                    <p class="text-sm text-muted-foreground">
                        Enter hashtags separated by spaces or commas. Include the # symbol.
                    </p>
                    <p v-if="form.errors.hashtags" class="text-sm text-destructive">
                        {{ form.errors.hashtags }}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Creating...' : 'Create Group' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
