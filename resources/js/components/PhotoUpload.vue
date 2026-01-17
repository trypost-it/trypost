<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { store as storeMedia, destroy as destroyMedia } from '@/routes/medias';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Trash2, Upload, User } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Photo {
    url: string;
    media_id: string | null;
}

interface Props {
    modelId: string;
    modelType: string;
    photo: Photo;
    collection?: string;
    reloadOnly?: string[];
    size?: 'sm' | 'md' | 'lg';
    rounded?: 'full' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
    collection: 'logo',
    reloadOnly: () => [],
    size: 'md',
    rounded: 'lg',
});

const photoPreview = ref<string | null>(props.photo.url);
const uploading = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);
const dialogOpen = ref(false);
const lightboxOpen = ref(false);

watch(
    () => props.photo.url,
    (newUrl) => {
        photoPreview.value = newUrl;
    },
);

const sizeClasses = {
    sm: 'h-16 w-16',
    md: 'h-20 w-20',
    lg: 'h-24 w-24',
};

const roundedClasses = {
    full: 'rounded-full',
    lg: 'rounded-lg',
};

const handlePhotoChange = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (!file) {
        return;
    }

    uploading.value = true;

    try {
        const formData = new FormData();
        formData.append('media', file);
        formData.append('collection', props.collection);
        formData.append('model', props.modelType);
        formData.append('model_id', props.modelId);

        await axios.post(storeMedia.url(), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        const reader = new FileReader();
        reader.onload = (e) => {
            photoPreview.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);

        if (props.reloadOnly.length > 0) {
            router.reload({ only: props.reloadOnly, preserveScroll: true });
        } else {
            router.reload({ preserveScroll: true });
        }

        closeDialog();
    } catch (error) {
        console.error('Error uploading photo:', error);
    } finally {
        uploading.value = false;
        if (fileInputRef.value) {
            fileInputRef.value.value = '';
        }
    }
};

const removePhoto = async () => {
    if (!props.photo.media_id) {
        return;
    }

    try {
        await axios.delete(
            destroyMedia.url({ modelId: props.modelId, media: props.photo.media_id! }),
        );

        if (props.reloadOnly.length > 0) {
            router.reload({ only: props.reloadOnly, preserveScroll: true });
        } else {
            router.reload({ preserveScroll: true });
        }
    } catch (error) {
        console.error('Error removing photo:', error);
    }
};

const triggerFileInput = () => {
    fileInputRef.value?.click();
};

const openDialog = () => {
    dialogOpen.value = true;
};

const closeDialog = () => {
    dialogOpen.value = false;
};
</script>

<template>
    <div class="flex items-center gap-4">
        <div class="relative">
            <img
                v-if="photoPreview"
                :src="photoPreview"
                alt="Preview"
                :class="[
                    'cursor-pointer border-2 border-muted object-cover transition-opacity hover:opacity-80',
                    sizeClasses[size],
                    roundedClasses[rounded],
                ]"
                @click="lightboxOpen = true"
            />
            <div
                v-else
                :class="[
                    'flex items-center justify-center bg-muted',
                    sizeClasses[size],
                    roundedClasses[rounded],
                ]"
            >
                <User class="h-8 w-8 text-muted-foreground" />
            </div>
        </div>

        <TooltipProvider>
            <div class="flex items-center gap-2">
                <input
                    ref="fileInputRef"
                    type="file"
                    accept="image/*"
                    class="hidden"
                    @change="handlePhotoChange"
                />

                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="px-2"
                            :disabled="uploading"
                            @click="openDialog"
                        >
                            <Upload class="h-4 w-4" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>
                            {{
                                uploading
                                    ? 'Uploading...'
                                    : photo.media_id
                                        ? 'Change photo'
                                        : 'Add photo'
                            }}
                        </p>
                    </TooltipContent>
                </Tooltip>

                <Tooltip v-if="photo.media_id">
                    <TooltipTrigger as-child>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="px-2"
                            @click="removePhoto"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>Remove photo</p>
                    </TooltipContent>
                </Tooltip>
            </div>
        </TooltipProvider>

        <Dialog :open="dialogOpen" @update:open="(open) => !open && closeDialog()">
            <DialogContent class="sm:max-w-md">
                <DialogTitle>Upload Photo</DialogTitle>
                <DialogDescription class="sr-only">
                    Select an image file to upload
                </DialogDescription>

                <div class="flex flex-col items-center gap-4 py-8">
                    <div class="rounded-full bg-muted p-6">
                        <Upload class="h-12 w-12 text-muted-foreground" />
                    </div>
                    <p class="text-center text-sm text-muted-foreground">
                        Select an image from your computer
                    </p>
                    <Button type="button" :disabled="uploading" @click="triggerFileInput">
                        {{ uploading ? 'Uploading...' : 'Choose file' }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="lightboxOpen">
            <DialogContent class="max-w-[95vw] overflow-hidden bg-black p-0 sm:max-w-3xl">
                <DialogTitle class="sr-only">View Photo</DialogTitle>
                <DialogDescription class="sr-only">Photo in full size</DialogDescription>
                <img
                    v-if="photoPreview"
                    :src="photoPreview"
                    alt="Preview"
                    class="h-auto max-h-[90vh] w-full object-contain"
                />
            </DialogContent>
        </Dialog>
    </div>
</template>
