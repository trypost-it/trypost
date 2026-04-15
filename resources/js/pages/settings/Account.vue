<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { update as accountUpdate } from '@/routes/app/account';
interface AccountData {
    id: string;
    name: string;
    billing_email: string;
}

const props = defineProps<{
    account: AccountData;
    selfHosted: boolean;
}>();

</script>

<template>
    <AppLayout :title="$t('settings.account.title')">
        <Head :title="$t('settings.account.title')" />

        <div class="mx-auto max-w-2xl space-y-6 p-6">
            <HeadingSmall
                :title="$t('settings.account.title')"
                :description="$t('settings.account.description')"
            />

            <Form
                v-bind="accountUpdate.form()"
                method="put"
                v-slot="{ errors, processing }"
                class="space-y-6"
            >
                <div class="grid gap-2">
                    <Label for="account-name">{{ $t('settings.account.name') }}</Label>
                    <Input
                        id="account-name"
                        name="name"
                        :default-value="account.name"
                        :placeholder="trans('settings.account.name_placeholder')"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div v-if="!selfHosted" class="grid gap-2">
                    <Label for="billing-email">{{ $t('settings.account.billing_email') }}</Label>
                    <Input
                        id="billing-email"
                        name="billing_email"
                        type="email"
                        :default-value="account.billing_email"
                        :placeholder="trans('settings.account.billing_email_placeholder')"
                    />
                    <p class="text-sm text-muted-foreground">
                        {{ $t('settings.account.billing_email_hint') }}
                    </p>
                    <InputError :message="errors.billing_email" />
                </div>

                <Button type="submit" :disabled="processing">
                    {{ $t('settings.account.submit') }}
                </Button>
            </Form>
        </div>
    </AppLayout>
</template>
