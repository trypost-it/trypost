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
import { update as brandsUpdate } from '@/routes/app/brands';

interface Brand {
    id: string;
    name: string;
}

const props = defineProps<{
    brand: Brand | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    name: '',
});

watch(() => props.brand, (brand) => {
    if (brand) {
        form.name = brand.name;
        form.clearErrors();
    }
}, { immediate: true });

const submit = () => {
    if (!props.brand) return;
    form.put(brandsUpdate.url(props.brand.id), {
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
                <DialogTitle>{{ $t('brands.edit.title') }}</DialogTitle>
                <DialogDescription>
                    {{ $t('brands.edit.description') }}
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="edit-brand-name">{{ $t('brands.edit.name') }}</Label>
                    <Input
                        id="edit-brand-name"
                        v-model="form.name"
                        :placeholder="trans('brands.edit.name_placeholder')"
                        :class="{ 'border-destructive': form.errors.name }"
                    />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? $t('brands.edit.submitting') : $t('brands.edit.submit') }}
                    </Button>
                    <Button type="button" variant="secondary" @click="open = false">
                        {{ $t('common.cancel') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
