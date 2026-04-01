<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { preferences as preferencesRoute } from '@/routes/app/notifications';
import { type BreadcrumbItem } from '@/types';

interface Preferences {
    post_published: boolean;
    post_failed: boolean;
    account_disconnected: boolean;
}

interface Props {
    preferences: Preferences;
}

const props = defineProps<Props>();

const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
    { title: trans('settings.title'), href: preferencesRoute().url },
    { title: trans('settings.nav.notifications'), href: preferencesRoute().url },
]);

const postPublished = ref(props.preferences.post_published);
const postFailed = ref(props.preferences.post_failed);
const accountDisconnected = ref(props.preferences.account_disconnected);
const processing = ref(false);

const submit = () => {
    processing.value = true;

    router.put(preferencesRoute().url, {
        post_published: postPublished.value,
        post_failed: postFailed.value,
        account_disconnected: accountDisconnected.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('settings.notifications.title')" />

        <h1 class="sr-only">{{ $t('settings.notifications.title') }}</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    :title="$t('settings.notifications.heading')"
                    :description="$t('settings.notifications.description')"
                />

                <div class="space-y-4">
                    <div class="flex items-center justify-between rounded-lg border p-4">
                        <div class="space-y-0.5">
                            <Label for="post_published">{{ $t('settings.notifications.post_published') }}</Label>
                            <p class="text-sm text-muted-foreground">
                                {{ $t('settings.notifications.post_published_description') }}
                            </p>
                        </div>
                        <Switch id="post_published" v-model="postPublished" />
                    </div>

                    <div class="flex items-center justify-between rounded-lg border p-4">
                        <div class="space-y-0.5">
                            <Label for="post_failed">{{ $t('settings.notifications.post_failed') }}</Label>
                            <p class="text-sm text-muted-foreground">
                                {{ $t('settings.notifications.post_failed_description') }}
                            </p>
                        </div>
                        <Switch id="post_failed" v-model="postFailed" />
                    </div>

                    <div class="flex items-center justify-between rounded-lg border p-4">
                        <div class="space-y-0.5">
                            <Label for="account_disconnected">{{ $t('settings.notifications.account_disconnected') }}</Label>
                            <p class="text-sm text-muted-foreground">
                                {{ $t('settings.notifications.account_disconnected_description') }}
                            </p>
                        </div>
                        <Switch id="account_disconnected" v-model="accountDisconnected" />
                    </div>
                </div>

                <Button :disabled="processing" class="self-start" @click="submit">
                    {{ $t('settings.notifications.save') }}
                </Button>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
