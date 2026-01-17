<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
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
import { update as labelsUpdate } from '@/routes/labels';

interface LabelType {
    id: string;
    name: string;
    color: string;
}

const props = defineProps<{
    label: LabelType | null;
}>();

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
    color: '',
});

watch(() => props.label, (label) => {
    if (label) {
        form.name = label.name;
        form.color = label.color;
        form.clearErrors();
    }
}, { immediate: true });

const submit = () => {
    if (!props.label) return;
    form.put(labelsUpdate.url(props.label.id), {
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
                <DialogTitle>Edit Label</DialogTitle>
                <DialogDescription>
                    Update the name and color for this label
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="edit-name">Name</Label>
                    <Input
                        id="edit-name"
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

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
