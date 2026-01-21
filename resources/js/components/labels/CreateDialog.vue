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
import { store as labelsStore } from '@/routes/labels';

const open = defineModel<boolean>('open', { default: false });

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
    name: '',
    color: colors[0],
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
        form.color = colors[0];
        form.clearErrors();
    }
    open.value = value;
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ $t('labels.create.title') }}</DialogTitle>
                <DialogDescription>
                    {{ $t('labels.create.description') }}
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submit" class="space-y-4">
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
                    <Label>{{ $t('labels.create.color') }}</Label>
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

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? $t('labels.create.submitting') : $t('labels.create.submit') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
