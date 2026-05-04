<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { IconDeviceDesktop, IconDeviceMobile } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

import AuthenticationController from '@/actions/App/Http/Controllers/App/Settings/AuthenticationController';
import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import SettingsTabsNav from '@/components/settings/SettingsTabsNav.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import { isMobileDevice, parseBrowserName, parseOsName } from '@/lib/userAgent';
import { settings as settingsHub } from '@/routes/app';
import { connectProvider, edit as editAuthentication } from '@/routes/app/authentication';
import { preferences as notificationPreferences } from '@/routes/app/notifications';
import { edit as editProfile } from '@/routes/app/profile';
import type { BreadcrumbItem } from '@/types';

type Session = {
    id: string;
    ip_address: string | null;
    user_agent: string | null;
    last_active: string;
    is_current: boolean;
};

type ConnectedAccount = {
    provider: 'google' | 'github';
    label: string;
    connected: boolean;
    can_disconnect: boolean;
};

const props = defineProps<{
    sessions: Session[];
    hasPassword: boolean;
    connectedAccounts: ConnectedAccount[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: trans('settings.hub.title'), href: settingsHub().url },
    { title: trans('settings.profile.title'), href: editProfile().url },
    { title: trans('settings.authentication.title') },
]);

const tabs = computed(() => [
    { name: 'profile', label: trans('settings.nav.profile'), href: editProfile().url },
    { name: 'authentication', label: trans('settings.nav.authentication'), href: editAuthentication().url },
    { name: 'notifications', label: trans('settings.nav.notifications'), href: notificationPreferences().url },
]);


const passwordHeading = computed(() =>
    props.hasPassword
        ? trans('settings.authentication.password.update_title')
        : trans('settings.authentication.password.set_title'),
);

const passwordDescription = computed(() =>
    props.hasPassword
        ? trans('settings.authentication.password.update_description')
        : trans('settings.authentication.password.set_description'),
);

const logoutDialogOpen = ref(false);
</script>

<template>
    <Head :title="$t('settings.authentication.page_title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl space-y-6 px-4 py-6">
            <SettingsTabsNav :tabs="tabs" active="authentication" />

            <section class="space-y-12">
                <div class="space-y-6">
                    <HeadingSmall
                        :title="$t('settings.authentication.sessions.title')"
                        :description="$t('settings.authentication.sessions.description')"
                    />

                    <div class="space-y-2">
                        <div
                            v-for="session in sessions"
                            :key="session.id"
                            :class="[
                                'flex items-center gap-4 rounded-lg border p-4 transition-colors',
                                session.is_current
                                    ? 'border-emerald-500/30 bg-emerald-500/[0.04] dark:border-emerald-400/25 dark:bg-emerald-500/[0.06]'
                                    : 'border-border',
                            ]"
                            data-test="session-row"
                        >
                            <div
                                :class="[
                                    'flex size-10 flex-shrink-0 items-center justify-center rounded-full',
                                    session.is_current
                                        ? 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-400'
                                        : 'bg-muted text-muted-foreground',
                                ]"
                            >
                                <component
                                    :is="isMobileDevice(session.user_agent) ? IconDeviceMobile : IconDeviceDesktop"
                                    class="size-5"
                                />
                            </div>
                            <div class="flex-1 space-y-0.5">
                                <div class="text-sm font-medium">
                                    {{ parseBrowserName(session.user_agent) }}
                                    <span
                                        v-if="parseOsName(session.user_agent)"
                                        class="font-normal text-muted-foreground"
                                    >
                                        {{ $t('settings.authentication.sessions.on') }} {{ parseOsName(session.user_agent) }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <span>{{ session.ip_address ?? $t('settings.authentication.sessions.unknown_ip') }}</span>
                                    <span aria-hidden="true">·</span>
                                    <template v-if="session.is_current">
                                        <span class="relative flex size-2">
                                            <span
                                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400/60"
                                            />
                                            <span class="relative inline-flex size-2 rounded-full bg-emerald-500" />
                                        </span>
                                        <span class="font-medium text-emerald-700 dark:text-emerald-400">
                                            {{ $t('settings.authentication.sessions.active_now') }}
                                        </span>
                                    </template>
                                    <template v-else>
                                        <span>{{ session.last_active }}</span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <Dialog v-model:open="logoutDialogOpen">
                        <DialogTrigger as-child>
                            <Button
                                variant="outline"
                                data-test="log-out-other-sessions-button"
                                :disabled="sessions.length <= 1"
                            >
                                {{ $t('settings.authentication.sessions.log_out_others') }}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="AuthenticationController.destroyOtherSessions.form()"
                                :options="{ preserveScroll: true }"
                                reset-on-success
                                @success="logoutDialogOpen = false"
                                class="space-y-6"
                                v-slot="{ errors, processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle>{{ $t('settings.authentication.sessions.modal_title') }}</DialogTitle>
                                    <DialogDescription>
                                        {{ hasPassword
                                            ? $t('settings.authentication.sessions.modal_description_password')
                                            : $t('settings.authentication.sessions.modal_description_email') }}
                                    </DialogDescription>
                                </DialogHeader>

                                <div v-if="hasPassword" class="grid gap-2">
                                    <Label for="session_password" class="sr-only">
                                        {{ $t('settings.authentication.password.current_password') }}
                                    </Label>
                                    <Input
                                        id="session_password"
                                        type="password"
                                        name="password"
                                        :placeholder="trans('settings.authentication.sessions.password_placeholder')"
                                    />
                                    <InputError :message="errors.password" />
                                </div>

                                <div v-else class="grid gap-2">
                                    <Label for="session_email_confirmation" class="sr-only">
                                        Email
                                    </Label>
                                    <Input
                                        id="session_email_confirmation"
                                        type="email"
                                        name="email_confirmation"
                                        :placeholder="trans('settings.authentication.sessions.email_placeholder')"
                                        autocomplete="off"
                                    />
                                    <InputError :message="errors.email_confirmation" />
                                </div>

                                <DialogFooter class="gap-2">
                                    <Button type="submit" :disabled="processing">
                                        {{ $t('settings.authentication.sessions.submit') }}
                                    </Button>
                                    <DialogClose as-child>
                                        <Button variant="secondary">
                                            {{ $t('settings.authentication.sessions.cancel') }}
                                        </Button>
                                    </DialogClose>
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>

                <Separator />

                <div class="space-y-6">
                    <HeadingSmall :title="passwordHeading" :description="passwordDescription" />

                    <Form
                        v-bind="AuthenticationController.updatePassword.form()"
                        :options="{ preserveScroll: true }"
                        reset-on-success
                        :reset-on-error="['password', 'password_confirmation', 'current_password']"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <div v-if="hasPassword" class="grid gap-2">
                            <Label for="current_password">{{ $t('settings.authentication.password.current_password') }}</Label>
                            <Input
                                id="current_password"
                                name="current_password"
                                type="password"
                                autocomplete="current-password"
                            />
                            <InputError :message="errors.current_password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password">{{ $t('settings.authentication.password.new_password') }}</Label>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="new-password"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password_confirmation">{{ $t('settings.authentication.password.confirm_password') }}</Label>
                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                autocomplete="new-password"
                            />
                            <InputError :message="errors.password_confirmation" />
                        </div>

                        <Button :disabled="processing" data-test="update-password-button">
                            {{ hasPassword
                                ? $t('settings.authentication.password.save')
                                : $t('settings.authentication.password.set') }}
                        </Button>
                    </Form>
                </div>

                <Separator />

                <div class="space-y-6">
                    <HeadingSmall
                        :title="$t('settings.authentication.providers.title')"
                        :description="$t('settings.authentication.providers.description')"
                    />

                    <div class="space-y-2">
                        <div
                            v-for="account in connectedAccounts"
                            :key="account.provider"
                            class="flex items-center gap-4 rounded-lg border p-4"
                            :data-test="`connected-account-${account.provider}`"
                        >
                            <div class="flex size-10 flex-shrink-0 items-center justify-center rounded-full bg-muted">
                                <img
                                    :src="`/images/social/${account.provider}.svg`"
                                    :alt="account.label"
                                    :class="['size-5', account.provider === 'github' ? 'dark:invert' : '']"
                                />
                            </div>
                            <div class="flex-1 space-y-0.5">
                                <div class="text-sm font-medium">{{ account.label }}</div>
                                <div
                                    v-if="account.connected"
                                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                                >
                                    <span class="size-1.5 rounded-full bg-emerald-500" />
                                    <span>{{ $t('settings.authentication.providers.connected') }}</span>
                                </div>
                                <div v-else class="text-xs text-muted-foreground">
                                    {{ $t('settings.authentication.providers.not_connected') }}
                                </div>
                            </div>
                            <Form
                                v-if="account.connected && account.can_disconnect"
                                v-bind="AuthenticationController.disconnectProvider.form(account.provider)"
                                :options="{ preserveScroll: true }"
                                #default="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    variant="ghost"
                                    size="sm"
                                    :disabled="processing"
                                    class="text-muted-foreground hover:text-destructive"
                                    :data-test="`disconnect-${account.provider}`"
                                >
                                    {{ $t('settings.authentication.providers.disconnect') }}
                                </Button>
                            </Form>
                            <Link
                                v-else-if="!account.connected"
                                :href="connectProvider(account.provider).url"
                            >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :data-test="`connect-${account.provider}`"
                                >
                                    {{ $t('settings.authentication.providers.connect') }}
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>

                <Separator />

                <DeleteUser :has-password="hasPassword" />
            </section>
        </div>
    </AppLayout>
</template>
