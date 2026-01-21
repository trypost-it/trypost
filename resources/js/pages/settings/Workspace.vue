<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import PhotoUpload from '@/components/PhotoUpload.vue';
import TimezoneCombobox from '@/components/TimezoneCombobox.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { settings } from '@/routes/workspace';
import { update } from '@/routes/workspace/settings';
import { type BreadcrumbItem } from '@/types';

interface Workspace {
    id: string;
    name: string;
    timezone: string;
    logo: {
        url: string;
        media_id: string | null;
    };
}

interface Props {
    workspace: Workspace;
    timezones: Record<string, string>;
}

const props = defineProps<Props>();

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    {
        title: trans('settings.workspace.title'),
        href: settings().url,
    },
]);

const form = useForm({
    name: props.workspace.name,
    timezone: props.workspace.timezone,
});

const submit = () => {
    form.put(update().url);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('settings.workspace.title')" />

        <h1 class="sr-only">{{ $t('settings.workspace.title') }}</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    :title="$t('settings.workspace.heading')"
                    :description="$t('settings.workspace.description')"
                />

                <div class="grid gap-2">
                    <Label>{{ $t('settings.workspace.logo') }}</Label>
                    <PhotoUpload
                        :model-id="workspace.id"
                        model-type="App\Models\Workspace"
                        :photo="workspace.logo"
                        collection="logo"
                        :reload-only="['workspace']"
                        rounded="full"
                    />
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="name">{{ $t('settings.workspace.name') }}</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            class="mt-1 block w-full"
                            required
                            :placeholder="trans('settings.workspace.name_placeholder')"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="timezone">{{ $t('settings.workspace.timezone') }}</Label>
                        <TimezoneCombobox
                            v-model="form.timezone"
                            :timezones="timezones"
                        />
                        <InputError class="mt-2" :message="form.errors.timezone" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">{{ $t('settings.workspace.save') }}</Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="form.recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                {{ $t('settings.workspace.saved') }}
                            </p>
                        </Transition>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
