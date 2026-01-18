<script setup lang="ts">
import { ref } from 'vue';
import { IconInfoCircle } from '@tabler/icons-vue';

import PopupLayout from '@/layouts/PopupLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { authorize as authorizeMastodon } from '@/routes/social/mastodon';

interface Props {
    errors?: Record<string, string>;
}

const props = defineProps<Props>();

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
    <PopupLayout title="Connect Mastodon">
        <div class="max-w-md mx-auto">
            <div class="flex items-center gap-3 mb-6">
                <img src="/images/accounts/mastodon.png" alt="Mastodon" class="h-12 w-12" />
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Connect Mastodon</h1>
                    <p class="text-sm text-muted-foreground">Enter your Mastodon instance</p>
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
                    <Label for="instance">Instance URL</Label>
                    <Input
                        id="instance"
                        name="instance"
                        v-model="instance"
                        type="url"
                        placeholder="https://mastodon.social"
                        :class="{ 'border-destructive': errors?.instance }"
                        required
                    />
                    <p v-if="errors?.instance" class="text-sm text-destructive">
                        {{ errors.instance }}
                    </p>
                </div>

                <Alert>
                    <IconInfoCircle class="h-4 w-4" />
                    <AlertDescription class="inline">Enter your Mastodon instance URL (e.g., mastodon.social, techhub.social)</AlertDescription>
                </Alert>

                <Button type="submit" :disabled="isSubmitting" class="w-full">
                    {{ isSubmitting ? 'Connecting...' : 'Continue with Mastodon' }}
                </Button>
            </form>
        </div>
    </PopupLayout>
</template>
