<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

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
                <DialogTitle>{{ $t('hashtags.create.title') }}</DialogTitle>
                <DialogDescription>
                    {{ $t('hashtags.create.description') }}
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="create-name">{{ $t('hashtags.create.name') }}</Label>
                    <Input
                        id="create-name"
                        v-model="form.name"
                        :placeholder="trans('hashtags.create.name_placeholder')"
                        :class="{ 'border-destructive': form.errors.name }"
                    />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="create-hashtags">{{ $t('hashtags.create.hashtags') }}</Label>
                    <Textarea
                        id="create-hashtags"
                        v-model="form.hashtags"
                        :placeholder="trans('hashtags.create.hashtags_placeholder')"
                        rows="4"
                        :class="{ 'border-destructive': form.errors.hashtags }"
                    />
                    <p class="text-sm text-muted-foreground">
                        {{ $t('hashtags.create.hashtags_hint') }}
                    </p>
                    <p v-if="form.errors.hashtags" class="text-sm text-destructive">
                        {{ form.errors.hashtags }}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? $t('hashtags.create.submitting') : $t('hashtags.create.submit') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
