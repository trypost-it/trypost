<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    email?: string | null;
    redirect?: string | null;
}>();

// Get user's timezone from browser
const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
</script>

<template>
    <AuthBase
        :title="$t('auth.register.title')"
        :description="$t('auth.register.description')"
    >
        <Head :title="$t('auth.register.page_title')" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <input v-if="redirect" type="hidden" name="redirect" :value="redirect" />
            <input type="hidden" name="timezone" :value="timezone" />
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">{{ $t('auth.register.name') }}</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        name="name"
                        :placeholder="$t('auth.register.name_placeholder')"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">{{ $t('auth.register.email') }}</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        name="email"
                        placeholder="email@example.com"
                        :default-value="email ?? ''"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">{{ $t('auth.register.password') }}</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        name="password"
                        :placeholder="$t('auth.register.password')"
                    />
                    <InputError :message="errors.password" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    tabindex="4"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    {{ $t('auth.register.submit') }}
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                {{ $t('auth.register.has_account') }}
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="5"
                    >{{ $t('auth.register.log_in') }}</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
