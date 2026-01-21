<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';

import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/user-password';
import { type BreadcrumbItem } from '@/types';

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    {
        title: trans('settings.password.title'),
        href: edit().url,
    },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('settings.password.title')" />

        <h1 class="sr-only">{{ $t('settings.password.title') }}</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    :title="$t('settings.password.heading')"
                    :description="$t('settings.password.description')"
                />

                <Form
                    v-bind="PasswordController.update.form()"
                    :options="{
                        preserveScroll: true,
                    }"
                    reset-on-success
                    :reset-on-error="[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="current_password">{{ $t('settings.password.current_password') }}</Label>
                        <Input
                            id="current_password"
                            name="current_password"
                            type="password"
                            autocomplete="current-password"
                            :placeholder="trans('settings.password.current_password_placeholder')"
                        />
                        <InputError :message="errors.current_password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">{{ $t('settings.password.new_password') }}</Label>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="trans('settings.password.new_password_placeholder')"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">{{ $t('settings.password.confirm_password') }}</Label>
                        <Input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="trans('settings.password.confirm_password_placeholder')"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>

                    <Button
                        :disabled="processing"
                        data-test="update-password-button"
                    >
                        {{ $t('settings.password.save') }}
                    </Button>
                </Form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
