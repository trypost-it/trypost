<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import WorkspaceController from '@/actions/App/Http/Controllers/App/WorkspaceController';
import BrandForm from '@/components/BrandForm.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';

interface Workspace {
    id: string;
    name: string;
    brand_website: string | null;
    brand_description: string | null;
    brand_tone: string;
    brand_voice_notes: string | null;
    brand_color: string | null;
    background_color: string | null;
    text_color: string | null;
    brand_font: string;
    image_style: string;
    content_language: string;
}

const props = defineProps<{
    workspace: Workspace;
    availableFonts: string[];
    availableImageStyles: string[];
}>();

const form = useForm({
    name: props.workspace.name,
    brand_website: props.workspace.brand_website ?? '',
    brand_description: props.workspace.brand_description ?? '',
    brand_tone: props.workspace.brand_tone ?? 'professional',
    brand_voice_notes: props.workspace.brand_voice_notes ?? '',
    brand_color: props.workspace.brand_color,
    background_color: props.workspace.background_color,
    text_color: props.workspace.text_color,
    brand_font: props.workspace.brand_font ?? 'Inter',
    image_style: props.workspace.image_style ?? 'cinematic',
    content_language: props.workspace.content_language ?? 'en',
});

const submit = () => {
    form.put(WorkspaceController.updateSettings.url());
};
</script>

<template>
    <form class="flex flex-col space-y-6" @submit.prevent="submit">
        <HeadingSmall
            :title="$t('settings.brand.title')"
            :description="$t('settings.brand.description')"
        />

        <BrandForm
            :fields="form"
            :errors="form.errors"
            :available-fonts="availableFonts"
            :available-image-styles="availableImageStyles"
        />

        <Button :disabled="form.processing">{{ $t('settings.workspace.save') }}</Button>
    </form>
</template>
