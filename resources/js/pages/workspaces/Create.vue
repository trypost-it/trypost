<script setup lang="ts">
import { Head, useForm, useHttp } from '@inertiajs/vue3';
import { IconLoader2, IconSparkles } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

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
import { autofill as autofillBrand, store as storeWorkspace } from '@/routes/app/workspaces';

const form = useForm({
    name: '',
    brand_website: '',
    brand_description: '',
    brand_tone: 'professional',
    brand_voice_notes: '',
    content_language: 'en',
    logo_url: '' as string | null,
});

const isAutofilling = ref(false);
const logoPreview = ref<string | null>(null);

interface AutofillResponse {
    name: string | null;
    brand_description: string | null;
    content_language: string | null;
    brand_tone: string | null;
    brand_voice_notes: string | null;
    logo_url: string | null;
}

const autofillHttp = useHttp<{ url: string }, AutofillResponse>({ url: '' });

const toneLabel = computed(() =>
    form.brand_tone ? trans(`workspaces.create.tone_${form.brand_tone}`) : '',
);

const languageLabel = computed(() => {
    const map: Record<string, string> = {
        en: 'English',
        'pt-BR': 'Português (Brasil)',
        es: 'Español',
    };
    return map[form.content_language] ?? '';
});

const submit = () => {
    form.post(storeWorkspace.url());
};

const runAutofill = async () => {
    const url = form.brand_website.trim();

    if (! url) {
        toast.error(trans('workspaces.create.autofill_missing_url'));
        return;
    }

    isAutofilling.value = true;

    try {
        autofillHttp.url = url;

        const data = await autofillHttp.post(autofillBrand.url());

        if (data?.name) form.name = data.name;
        if (data?.brand_description) form.brand_description = data.brand_description;
        if (data?.content_language) form.content_language = data.content_language;
        if (data?.brand_tone) form.brand_tone = data.brand_tone;
        if (data?.brand_voice_notes) form.brand_voice_notes = data.brand_voice_notes;

        if (data?.logo_url) {
            form.logo_url = data.logo_url;
            logoPreview.value = data.logo_url;
        }

        toast.success(trans('workspaces.create.autofill_success'));
    } catch (error) {
        const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message;
        toast.error(message ?? trans('workspaces.create.autofill_error'));
    } finally {
        isAutofilling.value = false;
    }
};
</script>

<template>
    <Head :title="$t('workspaces.create.page_title')" />

    <AuthLayout
        :title="$t('workspaces.create.title')"
        :description="$t('workspaces.create.description')"
    >
        <form class="flex flex-col gap-5" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="brand_website">{{ $t('workspaces.create.website') }}</Label>
                    <div class="flex gap-2">
                        <Input
                            id="brand_website"
                            v-model="form.brand_website"
                            type="url"
                            :placeholder="$t('workspaces.create.website_placeholder')"
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
                            {{ $t('workspaces.create.autofill') }}
                        </Button>
                    </div>
                    <p v-if="logoPreview" class="flex items-center gap-2 text-xs text-muted-foreground">
                        <img :src="logoPreview" alt="" class="h-6 w-6 rounded object-cover" />
                        {{ $t('workspaces.create.logo_captured') }}
                    </p>
                    <InputError :message="form.errors.brand_website" />
                </div>

                <div class="grid gap-2">
                    <Label for="name">{{ $t('workspaces.create.name') }}</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        :placeholder="$t('workspaces.create.name_placeholder')"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="brand_description">{{ $t('workspaces.create.brand_description') }}</Label>
                    <Textarea
                        id="brand_description"
                        v-model="form.brand_description"
                        :placeholder="$t('workspaces.create.brand_description_placeholder')"
                        rows="3"
                    />
                    <InputError :message="form.errors.brand_description" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="brand_tone">{{ $t('workspaces.create.tone') }}</Label>
                        <Select v-model="form.brand_tone">
                            <SelectTrigger id="brand_tone" class="w-full">
                                <SelectValue :placeholder="$t('workspaces.create.tone')">
                                    {{ toneLabel }}
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="professional">{{ $t('workspaces.create.tone_professional') }}</SelectItem>
                                <SelectItem value="casual">{{ $t('workspaces.create.tone_casual') }}</SelectItem>
                                <SelectItem value="friendly">{{ $t('workspaces.create.tone_friendly') }}</SelectItem>
                                <SelectItem value="bold">{{ $t('workspaces.create.tone_bold') }}</SelectItem>
                                <SelectItem value="inspirational">{{ $t('workspaces.create.tone_inspirational') }}</SelectItem>
                                <SelectItem value="humorous">{{ $t('workspaces.create.tone_humorous') }}</SelectItem>
                                <SelectItem value="educational">{{ $t('workspaces.create.tone_educational') }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.brand_tone" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="content_language">{{ $t('workspaces.create.content_language') }}</Label>
                        <Select v-model="form.content_language">
                            <SelectTrigger id="content_language" class="w-full">
                                <SelectValue :placeholder="$t('workspaces.create.content_language')">
                                    {{ languageLabel }}
                                </SelectValue>
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
                    {{ $t('workspaces.create.content_language_description') }}
                </p>

                <div class="grid gap-2">
                    <Label for="brand_voice_notes">{{ $t('workspaces.create.voice_notes') }}</Label>
                    <Textarea
                        id="brand_voice_notes"
                        v-model="form.brand_voice_notes"
                        :placeholder="$t('workspaces.create.voice_notes_placeholder')"
                        rows="3"
                    />
                    <InputError :message="form.errors.brand_voice_notes" />
                </div>

            <Button type="submit" class="w-full" :disabled="form.processing">
                {{ $t('workspaces.create.submit') }}
            </Button>
        </form>
    </AuthLayout>
</template>
