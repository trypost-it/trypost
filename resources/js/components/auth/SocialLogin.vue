<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { IconBrandGithub } from '@tabler/icons-vue';
import { computed } from 'vue';

import { Button } from '@/components/ui/button';
import { redirect as githubRedirect } from '@/routes/auth/github';
import { redirect as googleRedirect } from '@/routes/auth/google';

defineProps<{
    mode: 'login' | 'signup';
}>();

const page = usePage();
const googleEnabled = computed(() => Boolean(page.props.googleAuthEnabled));
const githubEnabled = computed(() => Boolean(page.props.githubAuthEnabled));
const hasSocial = computed(() => googleEnabled.value || githubEnabled.value);
</script>

<template>
    <template v-if="hasSocial">
        <div class="flex flex-col gap-2">
            <Button v-if="googleEnabled" variant="outline" class="w-full" as="a" :href="googleRedirect.url()">
                <img src="/images/social/google.svg" alt="Google" class="size-4" />
                {{ mode === 'login' ? $t('auth.google_login') : $t('auth.google_signup') }}
            </Button>

            <Button v-if="githubEnabled" variant="outline" class="w-full" as="a" :href="githubRedirect.url()">
                <IconBrandGithub class="size-4" />
                {{ mode === 'login' ? $t('auth.github_login') : $t('auth.github_signup') }}
            </Button>
        </div>

        <div
            class="relative text-center text-sm after:absolute after:inset-0 after:top-1/2 after:z-0 after:flex after:items-center after:border-t after:border-border"
        >
            <span class="relative z-10 bg-background px-2 text-muted-foreground">{{ $t('auth.or_continue_with') }}</span>
        </div>
    </template>
</template>
