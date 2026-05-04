<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { IconEye, IconEyeOff } from '@tabler/icons-vue';
import { ref } from 'vue';

import SocialLogin from '@/components/auth/SocialLogin.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    email?: string | null;
    redirect?: string | null;
}>();

const showPassword = ref(false);

const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
</script>

<template>
    <AuthBase
        :title="$t('auth.register.title')"
        :description="$t('auth.register.description')"
        :show-legal="true"
    >
        <Head :title="$t('auth.register.page_title')" />

        <div class="flex flex-col gap-6">
            <SocialLogin mode="signup" />

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
                        <div class="relative">
                            <Input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                :tabindex="3"
                                autocomplete="new-password"
                                name="password"
                                :placeholder="$t('auth.register.password')"
                            />
                            <div class="absolute inset-y-0 end-0 flex items-center pe-3">
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <button
                                                type="button"
                                                :tabindex="-1"
                                                class="cursor-pointer text-muted-foreground hover:text-foreground"
                                                @click="showPassword = !showPassword"
                                            >
                                                <IconEyeOff v-if="showPassword" class="size-4" />
                                                <IconEye v-else class="size-4" />
                                            </button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{{ showPassword ? $t('auth.register.hide_password') : $t('auth.register.show_password') }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                        </div>
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
        </div>
    </AuthBase>
</template>
