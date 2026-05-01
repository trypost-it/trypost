<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

import WorkspaceController from '@/actions/App/Http/Controllers/App/WorkspaceController';
import HeadingSmall from '@/components/HeadingSmall.vue';
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

interface Workspace {
    id: string;
    name: string;
    brand_website: string | null;
    brand_description: string | null;
    brand_tone: string;
    brand_voice_notes: string | null;
    content_language: string;
}

const props = defineProps<{
    workspace: Workspace;
}>();

const brandTone = ref(props.workspace.brand_tone ?? 'professional');
const contentLanguage = ref(props.workspace.content_language ?? 'en');

const toneLabel = computed(() =>
    brandTone.value ? trans(`settings.brand.tone_${brandTone.value}`) : '',
);

const languageLabel = computed(() => {
    const map: Record<string, string> = {
        en: 'English',
        'pt-BR': 'Português (Brasil)',
        es: 'Español',
    };
    return map[contentLanguage.value] ?? '';
});
</script>

<template>
    <div class="flex flex-col space-y-6">
        <HeadingSmall
            :title="$t('settings.brand.title')"
            :description="$t('settings.brand.description')"
        />

        <Form
            v-bind="WorkspaceController.updateSettings.form()"
            v-slot="{ errors, processing }"
            class="space-y-6"
        >
            <input type="hidden" name="name" :value="workspace.name" />

            <div class="grid gap-2">
                <Label for="brand_website">{{ $t('settings.brand.website') }}</Label>
                <Input
                    id="brand_website"
                    name="brand_website"
                    type="url"
                    :default-value="workspace.brand_website ?? ''"
                    :placeholder="$t('settings.brand.website_placeholder')"
                />
                <InputError :message="errors.brand_website" />
            </div>

            <div class="grid gap-2">
                <Label for="brand_description">{{ $t('settings.brand.brand_description') }}</Label>
                <Textarea
                    id="brand_description"
                    name="brand_description"
                    :default-value="workspace.brand_description ?? ''"
                    :placeholder="$t('settings.brand.brand_description_placeholder')"
                    rows="3"
                />
                <InputError :message="errors.brand_description" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="brand_tone">{{ $t('settings.brand.tone') }}</Label>
                    <Select v-model="brandTone" name="brand_tone">
                        <SelectTrigger id="brand_tone" class="w-full">
                            <SelectValue :placeholder="$t('settings.brand.tone')">
                                {{ toneLabel }}
                            </SelectValue>
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
                    <input type="hidden" name="brand_tone" :value="brandTone" />
                    <InputError :message="errors.brand_tone" />
                </div>

                <div class="grid gap-2">
                    <Label for="content_language">{{ $t('settings.brand.content_language') }}</Label>
                    <Select v-model="contentLanguage" name="content_language">
                        <SelectTrigger id="content_language" class="w-full">
                            <SelectValue :placeholder="$t('settings.brand.content_language')">
                                {{ languageLabel }}
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="en">English</SelectItem>
                            <SelectItem value="pt-BR">Português (Brasil)</SelectItem>
                            <SelectItem value="es">Español</SelectItem>
                        </SelectContent>
                    </Select>
                    <input type="hidden" name="content_language" :value="contentLanguage" />
                    <InputError :message="errors.content_language" />
                </div>
            </div>

            <p class="-mt-4 text-xs text-muted-foreground">
                {{ $t('settings.brand.content_language_description') }}
            </p>

            <div class="grid gap-2">
                <Label for="brand_voice_notes">{{ $t('settings.brand.voice_notes') }}</Label>
                <Textarea
                    id="brand_voice_notes"
                    name="brand_voice_notes"
                    :default-value="workspace.brand_voice_notes ?? ''"
                    :placeholder="$t('settings.brand.voice_notes_placeholder')"
                    rows="3"
                />
                <InputError :message="errors.brand_voice_notes" />
            </div>

            <Button :disabled="processing">{{ $t('settings.workspace.save') }}</Button>
        </Form>
    </div>
</template>
