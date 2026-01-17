<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { Loader2, CheckCircle, XCircle } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';

import { Button } from '@/components/ui/button';
import { subscribe } from '@/routes';
import { index as workspacesIndex } from '@/routes/workspaces';

interface Props {
    userId: number;
    status: 'processing' | 'success' | 'cancelled';
}

const props = defineProps<Props>();

const currentStatus = ref(props.status);

// Listen for subscription created event
if (props.status === 'processing') {
    useEcho(
        `users.${props.userId}`,
        'SubscriptionCreated',
        () => {
            currentStatus.value = 'success';
            setTimeout(() => {
                router.visit(workspacesIndex.url());
            }, 1500);
        },
    );

    // Fallback: check subscription status after 10 seconds
    onMounted(() => {
        setTimeout(() => {
            if (currentStatus.value === 'processing') {
                router.visit(workspacesIndex.url());
            }
        }, 10000);
    });
}

// If already success, redirect after a moment
if (props.status === 'success') {
    onMounted(() => {
        setTimeout(() => {
            router.visit(workspacesIndex.url());
        }, 1500);
    });
}

function retry() {
    router.visit(subscribe.url());
}
</script>

<template>
    <Head title="Processing..." />

    <div class="min-h-screen bg-gradient-to-b from-background via-background to-muted/30 flex items-center justify-center">
        <div class="text-center max-w-md px-4">
            <!-- Processing -->
            <template v-if="currentStatus === 'processing'">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-primary/10 mb-6">
                    <Loader2 class="w-10 h-10 text-primary animate-spin" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight mb-3">Processing your subscription</h1>
                <p class="text-muted-foreground">
                    Please wait while we set up your account. This will only take a moment.
                </p>
            </template>

            <!-- Success -->
            <template v-else-if="currentStatus === 'success'">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100 mb-6">
                    <CheckCircle class="w-10 h-10 text-green-600" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight mb-3">You're all set!</h1>
                <p class="text-muted-foreground">
                    Your subscription is active. Redirecting you to your workspaces...
                </p>
            </template>

            <!-- Cancelled -->
            <template v-else-if="currentStatus === 'cancelled'">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-red-100 mb-6">
                    <XCircle class="w-10 h-10 text-red-600" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight mb-3">Checkout cancelled</h1>
                <p class="text-muted-foreground mb-6">
                    Your checkout was cancelled. No charges were made.
                </p>
                <Button @click="retry">
                    Try again
                </Button>
            </template>
        </div>
    </div>
</template>
