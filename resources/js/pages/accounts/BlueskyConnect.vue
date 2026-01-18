<script setup lang="ts">
import { ref } from 'vue';
import { IconInfoCircle } from '@tabler/icons-vue';

import PopupLayout from '@/layouts/PopupLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { store as storeBluesky } from '@/routes/social/bluesky';

interface Props {
    errors?: Record<string, string>;
}

const props = defineProps<Props>();

const formRef = ref<HTMLFormElement | null>(null);
const identifier = ref('');
const password = ref('');
const isSubmitting = ref(false);

const submit = () => {
    isSubmitting.value = true;
    formRef.value?.submit();
};

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
</script>

<template>
    <PopupLayout title="Connect Bluesky">
        <div class="max-w-md mx-auto">
            <div class="flex items-center gap-3 mb-6">
                <img src="/images/accounts/bluesky.png" alt="Bluesky" class="h-12 w-12" />
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Connect Bluesky</h1>
                    <p class="text-sm text-muted-foreground">Enter your credentials to connect</p>
                </div>
            </div>

            <form
                ref="formRef"
                :action="storeBluesky.url()"
                method="POST"
                @submit.prevent="submit"
                class="space-y-4"
            >
                <input type="hidden" name="_token" :value="csrfToken" />

                <div class="space-y-2">
                    <Label for="identifier">Handle or Email</Label>
                    <Input
                        id="identifier"
                        name="identifier"
                        v-model="identifier"
                        type="text"
                        placeholder="yourhandle.bsky.social"
                        :class="{ 'border-destructive': errors?.identifier }"
                        required
                    />
                    <p v-if="errors?.identifier" class="text-sm text-destructive">
                        {{ errors.identifier }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="password">App Password</Label>
                    <Input
                        id="password"
                        name="password"
                        v-model="password"
                        type="password"
                        placeholder="xxxx-xxxx-xxxx-xxxx"
                        :class="{ 'border-destructive': errors?.password }"
                        required
                    />
                    <p v-if="errors?.password" class="text-sm text-destructive">
                        {{ errors.password }}
                    </p>
                </div>

                <Alert>
                    <IconInfoCircle class="h-4 w-4" />
                    <AlertDescription class="inline">
                        Use an <strong>App Password</strong> for security. Create one at <a href="https://bsky.app/settings/app-passwords" target="_blank" class="underline">bsky.app/settings</a>.
                    </AlertDescription>
                </Alert>

                <Button
                    type="submit"
                    :disabled="isSubmitting"
                    class="w-full"
                >
                    {{ isSubmitting ? 'Connecting...' : 'Connect Bluesky' }}
                </Button>
            </form>
        </div>
    </PopupLayout>
</template>
