<script setup lang="ts">
defineProps<{
    title?: string;
}>();
</script>

<template>
    <div class="min-h-svh bg-background">
        <!-- Header -->
        <header class="border-b">
            <div class="mx-auto max-w-5xl px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <img src="/images/trypost/logo-light.png" alt="TryPost" class="dark:hidden h-7 w-auto" />
                    <img src="/images/trypost/logo-dark.png" alt="TryPost" class="hidden dark:block h-7 w-auto" />
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="mx-auto max-w-5xl px-6 py-12">
            <h1 v-if="title" class="text-3xl font-bold mb-8">{{ title }}</h1>
            <slot />
        </main>

        <!-- Footer -->
        <footer class="border-t mt-auto">
            <div class="mx-auto max-w-5xl px-6 py-8">
                <p class="text-sm text-muted-foreground text-center">
                    &copy; {{ new Date().getFullYear() }} TryPost. All rights reserved.
                </p>
            </div>
        </footer>
    </div>
</template>
