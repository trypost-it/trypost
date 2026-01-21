<script setup lang="ts">
import { IconInfoCircle } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PopupLayout from '@/layouts/PopupLayout.vue';
import { authorize as authorizeMastodon } from '@/routes/social/mastodon';

interface Props {
    errors?: Record<string, string>;
}

defineProps<Props>();

const formRef = ref<HTMLFormElement | null>(null);
const instance = ref('https://mastodon.social');
const isSubmitting = ref(false);

const submit = () => {
    isSubmitting.value = true;
    formRef.value?.submit();
};

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
</script>

<template>
    <PopupLayout :title="$t('accounts.mastodon.title')">
        <div class="max-w-md mx-auto">
            <div class="flex items-center gap-3 mb-6">
                <img src="/images/accounts/mastodon.png" alt="Mastodon" class="h-12 w-12" />
                <div>
                    <h1 class="text-xl font-bold tracking-tight">{{ $t('accounts.mastodon.title') }}</h1>
                    <p class="text-sm text-muted-foreground">{{ $t('accounts.mastodon.description') }}</p>
                </div>
            </div>

            <form
                ref="formRef"
                :action="authorizeMastodon.url()"
                method="POST"
                @submit.prevent="submit"
                class="space-y-4"
            >
                <input type="hidden" name="_token" :value="csrfToken" />

                <div class="space-y-2">
                    <Label for="instance">{{ $t('accounts.mastodon.instance_url') }}</Label>
                    <Input
                        id="instance"
                        name="instance"
                        v-model="instance"
                        type="url"
                        :placeholder="trans('accounts.mastodon.instance_placeholder')"
                        :class="{ 'border-destructive': errors?.instance }"
                        required
                    />
                    <p v-if="errors?.instance" class="text-sm text-destructive">
                        {{ errors.instance }}
                    </p>
                </div>

                <Alert>
                    <IconInfoCircle class="h-4 w-4" />
                    <AlertDescription class="inline">{{ $t('accounts.mastodon.instance_hint') }}</AlertDescription>
                </Alert>

                <Button type="submit" :disabled="isSubmitting" class="w-full">
                    {{ isSubmitting ? $t('accounts.mastodon.submitting') : $t('accounts.mastodon.submit') }}
                </Button>
            </form>
        </div>
    </PopupLayout>
</template>
