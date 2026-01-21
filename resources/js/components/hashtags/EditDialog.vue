<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { watch } from 'vue';

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
import { update as hashtagsUpdate } from '@/routes/hashtags';

interface Hashtag {
    id: string;
    name: string;
    hashtags: string;
}

const props = defineProps<{
    hashtag: Hashtag | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    name: '',
    hashtags: '',
});

watch(() => props.hashtag, (hashtag) => {
    if (hashtag) {
        form.name = hashtag.name;
        form.hashtags = hashtag.hashtags;
        form.clearErrors();
    }
}, { immediate: true });

const submit = () => {
    if (!props.hashtag) return;
    form.put(hashtagsUpdate.url(props.hashtag.id), {
        onSuccess: () => {
            open.value = false;
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ $t('hashtags.edit.title') }}</DialogTitle>
                <DialogDescription>
                    {{ $t('hashtags.edit.description') }}
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="edit-name">{{ $t('hashtags.edit.name') }}</Label>
                    <Input
                        id="edit-name"
                        v-model="form.name"
                        :placeholder="trans('hashtags.edit.name_placeholder')"
                        :class="{ 'border-destructive': form.errors.name }"
                    />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="edit-hashtags">{{ $t('hashtags.edit.hashtags') }}</Label>
                    <Textarea
                        id="edit-hashtags"
                        v-model="form.hashtags"
                        :placeholder="trans('hashtags.edit.hashtags_placeholder')"
                        rows="4"
                        :class="{ 'border-destructive': form.errors.hashtags }"
                    />
                    <p class="text-sm text-muted-foreground">
                        {{ $t('hashtags.edit.hashtags_hint') }}
                    </p>
                    <p v-if="form.errors.hashtags" class="text-sm text-destructive">
                        {{ form.errors.hashtags }}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? $t('hashtags.edit.submitting') : $t('hashtags.edit.submit') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
