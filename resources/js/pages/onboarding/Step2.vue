<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

import { storeStep2 } from '@/actions/App/Http/Controllers/OnboardingController';
import SocialAccountsGrid, { type Platform } from '@/components/SocialAccountsGrid.vue';
import { Button } from '@/components/ui/button';
import OnboardingLayout from '@/layouts/OnboardingLayout.vue';

interface Props {
    platforms: Platform[];
    hasWorkspace: boolean;
}

const props = defineProps<Props>();

const isSubmitting = ref(false);

const connectedCount = computed(() => {
    return props.platforms.filter((p) => p.connected).length;
});

const submit = () => {
    isSubmitting.value = true;
    router.post(storeStep2.url());
};
</script>

<template>
    <Head title="Connect your accounts" />

    <OnboardingLayout
        title="Connect your accounts"
        description="Connect at least one social network to get started"
        :step="2"
        wide
    >
        <div v-if="hasWorkspace" class="space-y-6">
            <SocialAccountsGrid
                :platforms="platforms"
                :columns="2"
                :show-disconnect="false"
                :show-reconnect="false"
                :show-view-profile="false"
            />

            <div v-if="connectedCount > 0" class="flex flex-col items-center gap-4">
                <Button
                    class="w-full"
                    size="lg"
                    :disabled="isSubmitting"
                    @click="submit"
                >
                    Continue
                </Button>
            </div>
        </div>

        <div v-else class="flex flex-col items-center gap-4 py-8">
            <p class="text-muted-foreground">
                Something went wrong. Please try again.
            </p>
            <Button variant="outline" @click="router.visit('/onboarding/step1')">
                Go Back
            </Button>
        </div>
    </OnboardingLayout>
</template>
