<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';

import WorkspaceController from '@/actions/App/Http/Controllers/App/WorkspaceController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
</script>

<template>
    <Head :title="$t('workspaces.create.title')" />

    <AuthLayout
        :title="$t('workspaces.create.title')"
        :description="$t('workspaces.create.description')"
    >
        <Form
            v-bind="WorkspaceController.store.form()"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">{{ $t('workspaces.create.name') }}</Label>
                <Input
                    id="name"
                    name="name"
                    autofocus
                    :placeholder="trans('workspaces.create.name_placeholder')"
                />
                <InputError :message="errors.name" />
            </div>

            <Button type="submit" class="w-full" :disabled="processing">
                {{ $t('workspaces.create.submit') }}
            </Button>
        </Form>
    </AuthLayout>
</template>
