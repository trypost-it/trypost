<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import ProfileController from '@/actions/App/Http/Controllers/App/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import PhotoUpload from '@/components/PhotoUpload.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit, uploadPhoto, deletePhoto } from '@/routes/app/profile';
import { send } from '@/routes/verification';
import { type BreadcrumbItem } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const page = usePage();
const user = computed(() => page.props.auth.user);

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    { title: trans('settings.title'), href: edit().url },
    { title: trans('settings.nav.profile'), href: edit().url },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('settings.profile.title')" />

        <h1 class="sr-only">{{ $t('settings.profile.title') }}</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    :title="$t('settings.profile.photo_heading')"
                    :description="$t('settings.profile.photo_description')"
                />

                <PhotoUpload
                    :photo-url="user.photo_url"
                    :has-photo="user.has_photo"
                    :name="user.name"
                    :upload-url="uploadPhoto().url"
                    :delete-url="deletePhoto().url"
                />
            </div>

            <Separator />

            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    :title="$t('settings.profile.heading')"
                    :description="$t('settings.profile.description')"
                />

                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="name">{{ $t('settings.profile.name') }}</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="user.name"
                            required
                            autocomplete="name"
                            :placeholder="trans('settings.profile.name_placeholder')"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">{{ $t('settings.profile.email') }}</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            :placeholder="trans('settings.profile.email_placeholder')"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            {{ $t('settings.profile.email_unverified') }}
                            <Link
                                :href="send()"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                {{ $t('settings.profile.resend_verification') }}
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            {{ $t('settings.profile.verification_sent') }}
                        </div>
                    </div>

                    <Button
                        :disabled="processing"
                        data-test="update-profile-button"
                    >
                        {{ $t('settings.profile.save') }}
                    </Button>
                </Form>
            </div>

            <Separator />

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
