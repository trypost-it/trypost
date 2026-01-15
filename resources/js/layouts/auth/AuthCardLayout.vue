<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10">
        <div class="flex w-full max-w-md flex-col gap-6">
            <Link :href="home()" class="flex items-center gap-2 self-center font-medium">
                <div class="flex h-9 w-9 items-center justify-center">
                    <img src="/images/trypost/logo-light.png" alt="TryPost" class="dark:hidden h-8 w-auto" />
                    <img src="/images/trypost/logo-dark.png" alt="TryPost" class="hidden dark:block h-8 w-auto" />
                </div>
            </Link>

            <div class="flex flex-col gap-6">
                <Card class="rounded-xl">
                    <CardHeader class="px-10 pt-8 pb-0 text-center">
                        <CardTitle class="text-xl">{{ title }}</CardTitle>
                        <CardDescription>
                            {{ description }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="px-10 py-8">
                        <slot />
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>