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
import { store as brandsStore } from '@/routes/app/brands';

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    name: '',
});

const submit = () => {
    form.post(brandsStore.url(), {
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
                <DialogTitle>{{ $t('brands.create.title') }}</DialogTitle>
                <DialogDescription>
                    {{ $t('brands.create.description') }}
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="create-brand-name">{{ $t('brands.create.name') }}</Label>
                    <Input
                        id="create-brand-name"
                        v-model="form.name"
                        :placeholder="trans('brands.create.name_placeholder')"
                        :class="{ 'border-destructive': form.errors.name }"
                    />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? $t('brands.create.submitting') : $t('brands.create.submit') }}
                    </Button>
                    <Button type="button" variant="secondary" @click="open = false">
                        {{ $t('common.cancel') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
