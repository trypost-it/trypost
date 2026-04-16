<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconLoader2, IconSparkles } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

import { autofillBrand, skipBrand, storeBrand } from '@/actions/App/Http/Controllers/App/OnboardingController';
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
const isAutofilling = ref(false);
const logoPreview = ref<string | null>(null);

const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const submit = () => {
    form.post(storeBrand.url());
};

const skip = () => {
    skipForm.post(skipBrand.url());
};

const runAutofill = async () => {
    const url = form.brand_website.trim();

    if (! url) {
        toast.error(trans('onboarding.brand.autofill_missing_url'));
        return;
    }

    isAutofilling.value = true;

    try {
        const response = await fetch(autofillBrand.url(), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ url }),
        });

        if (! response.ok) {
            const body = await response.json().catch(() => ({}));
            toast.error(body.message ?? trans('onboarding.brand.autofill_error'));
            return;
        }

        const data = await response.json();

        if (data.brand_description) {
            form.brand_description = data.brand_description;
        }

        if (data.content_language) {
            form.content_language = data.content_language;
        }

        if (data.logo_url && data.has_logo) {
            logoPreview.value = data.logo_url;
        }

        toast.success(trans('onboarding.brand.autofill_success'));
    } catch {
        toast.error(trans('onboarding.brand.autofill_error'));
    } finally {
        isAutofilling.value = false;
    }
};
</script>

<template>
    <Head :title="$t('onboarding.brand.page_title')" />

    <AuthLayout
        :title="$t('onboarding.brand.title')"
        :description="$t('onboarding.brand.description')"
    >
        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="brand_website">{{ $t('onboarding.brand.website') }}</Label>
                <div class="flex gap-2">
                    <Input
                        id="brand_website"
                        v-model="form.brand_website"
                        type="url"
                        :placeholder="trans('onboarding.brand.website_placeholder')"
                        class="flex-1"
                    />
                    <Button
                        type="button"
                        variant="secondary"
                        :disabled="isAutofilling || !form.brand_website"
                        @click="runAutofill"
                    >
                        <IconLoader2 v-if="isAutofilling" class="h-4 w-4 animate-spin" />
                        <IconSparkles v-else class="h-4 w-4" />
                        {{ $t('onboarding.brand.autofill') }}
                    </Button>
                </div>
                <p v-if="logoPreview" class="flex items-center gap-2 text-xs text-muted-foreground">
                    <img :src="logoPreview" alt="" class="h-6 w-6 rounded object-cover" />
                    {{ $t('onboarding.brand.logo_captured') }}
                </p>
                <InputError :message="form.errors.brand_website" />
            </div>

            <div class="grid gap-2">
                <Label for="brand_description">{{ $t('onboarding.brand.brand_description') }}</Label>
                <Textarea
                    id="brand_description"
                    v-model="form.brand_description"
                    :placeholder="trans('onboarding.brand.brand_description_placeholder')"
                    rows="3"
                />
                <InputError :message="form.errors.brand_description" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="brand_tone">{{ $t('onboarding.brand.tone') }}</Label>
                    <Select v-model="form.brand_tone">
                        <SelectTrigger id="brand_tone" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="professional">{{ $t('onboarding.brand.tone_professional') }}</SelectItem>
                            <SelectItem value="casual">{{ $t('onboarding.brand.tone_casual') }}</SelectItem>
                            <SelectItem value="friendly">{{ $t('onboarding.brand.tone_friendly') }}</SelectItem>
                            <SelectItem value="bold">{{ $t('onboarding.brand.tone_bold') }}</SelectItem>
                            <SelectItem value="inspirational">{{ $t('onboarding.brand.tone_inspirational') }}</SelectItem>
                            <SelectItem value="humorous">{{ $t('onboarding.brand.tone_humorous') }}</SelectItem>
                            <SelectItem value="educational">{{ $t('onboarding.brand.tone_educational') }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.brand_tone" />
                </div>

                <div class="grid gap-2">
                    <Label for="content_language">{{ $t('onboarding.brand.content_language') }}</Label>
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
                {{ $t('onboarding.brand.content_language_description') }}
            </p>

            <div class="grid gap-2">
                <Label for="brand_voice_notes">{{ $t('onboarding.brand.voice_notes') }}</Label>
                <Textarea
                    id="brand_voice_notes"
                    v-model="form.brand_voice_notes"
                    :placeholder="trans('onboarding.brand.voice_notes_placeholder')"
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
