<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

import HexColorInput from '@/components/HexColorInput.vue';
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
import { store as labelsStore } from '@/routes/app/labels';

const open = defineModel<boolean>('open', { default: false });

const DEFAULT_COLOR = '#7c3aed';

const form = useForm({
    name: '',
    color: DEFAULT_COLOR,
});

const submit = () => {
    form.post(labelsStore.url(), {
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
};

const handleOpenChange = (value: boolean) => {
    if (value) {
        form.reset();
        form.color = DEFAULT_COLOR;
        form.clearErrors();
    }
    open.value = value;
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ $t('labels.create.title') }}</DialogTitle>
                <DialogDescription>
                    {{ $t('labels.create.description') }}
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submit" class="space-y-6">
                <div class="space-y-2">
                    <Label for="create-name">{{ $t('labels.create.name') }}</Label>
                    <Input
                        id="create-name"
                        v-model="form.name"
                        :placeholder="trans('labels.create.name_placeholder')"
                        :class="{ 'border-destructive': form.errors.name }"
                    />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="create-color">{{ $t('labels.create.color') }}</Label>
                    <HexColorInput v-model="form.color" name="color" />
                    <p v-if="form.errors.color" class="text-sm text-destructive">
                        {{ form.errors.color }}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? $t('labels.create.submitting') : $t('labels.create.submit') }}
                    </Button>
                    <Button type="button" variant="secondary" @click="open = false">
                        {{ $t('common.cancel') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
