<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

import { skipBrand, storeBrand } from '@/actions/App/Http/Controllers/App/OnboardingController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AuthLayout from '@/layouts/AuthLayout.vue';

interface Workspace {
    name: string;
    brand_website: string | null;
    brand_description: string | null;
    brand_tone: string;
    brand_voice_notes: string | null;
    content_language: string;
}

interface Props {
    workspace: Workspace;
}

const props = defineProps<Props>();

const form = useForm({
    brand_website: props.workspace.brand_website ?? '',
    brand_description: props.workspace.brand_description ?? '',
    brand_tone: props.workspace.brand_tone ?? 'professional',
    brand_voice_notes: props.workspace.brand_voice_notes ?? '',
    content_language: props.workspace.content_language ?? 'en',
});

const skipForm = useForm({});

const submit = () => {
    form.post(storeBrand.url());
};

const skip = () => {
    skipForm.post(skipBrand.url());
};

const isSkipping = ref(false);
</script>

<template>
    <Head :title="$t('onboarding.brand.page_title')" />

    <AuthLayout
        :title="$t('onboarding.brand.title')"
        :description="$t('onboarding.brand.description')"
    >
        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="brand_website">{{ $t('settings.brand.website') }}</Label>
                <Input
                    id="brand_website"
                    v-model="form.brand_website"
                    type="url"
                    :placeholder="trans('settings.brand.website_placeholder')"
                />
                <InputError :message="form.errors.brand_website" />
            </div>

            <div class="grid gap-2">
                <Label for="brand_description">{{ $t('settings.brand.brand_description') }}</Label>
                <Textarea
                    id="brand_description"
                    v-model="form.brand_description"
                    :placeholder="trans('settings.brand.brand_description_placeholder')"
                    rows="3"
                />
                <InputError :message="form.errors.brand_description" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="brand_tone">{{ $t('settings.brand.tone') }}</Label>
                    <Select v-model="form.brand_tone">
                        <SelectTrigger id="brand_tone" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="professional">{{ $t('settings.brand.tone_professional') }}</SelectItem>
                            <SelectItem value="casual">{{ $t('settings.brand.tone_casual') }}</SelectItem>
                            <SelectItem value="friendly">{{ $t('settings.brand.tone_friendly') }}</SelectItem>
                            <SelectItem value="bold">{{ $t('settings.brand.tone_bold') }}</SelectItem>
                            <SelectItem value="inspirational">{{ $t('settings.brand.tone_inspirational') }}</SelectItem>
                            <SelectItem value="humorous">{{ $t('settings.brand.tone_humorous') }}</SelectItem>
                            <SelectItem value="educational">{{ $t('settings.brand.tone_educational') }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.brand_tone" />
                </div>

                <div class="grid gap-2">
                    <Label for="content_language">{{ $t('settings.brand.content_language') }}</Label>
                    <Select v-model="form.content_language">
                        <SelectTrigger id="content_language" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="en">English</SelectItem>
                            <SelectItem value="pt-BR">Português (Brasil)</SelectItem>
                            <SelectItem value="es">Español</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.content_language" />
                </div>
            </div>

            <p class="-mt-2 text-xs text-muted-foreground">
                {{ $t('settings.brand.content_language_description') }}
            </p>

            <div class="grid gap-2">
                <Label for="brand_voice_notes">{{ $t('settings.brand.voice_notes') }}</Label>
                <Textarea
                    id="brand_voice_notes"
                    v-model="form.brand_voice_notes"
                    :placeholder="trans('settings.brand.voice_notes_placeholder')"
                    rows="3"
                />
                <InputError :message="form.errors.brand_voice_notes" />
            </div>

            <div class="mt-2 flex flex-col gap-2">
                <Button type="submit" class="w-full" :disabled="form.processing">
                    {{ $t('onboarding.brand.submit') }}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    class="w-full"
                    :disabled="skipForm.processing || form.processing"
                    @click="skip"
                >
                    {{ $t('onboarding.brand.skip') }}
                </Button>
            </div>
        </form>
    </AuthLayout>
</template>
