<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
}>();
</script>

<template>
    <div class="min-h-svh bg-background">
        <!-- Header -->
        <header class="border-b">
            <div class="mx-auto max-w-5xl px-6 py-4 flex items-center justify-between">
                <Link :href="home()" class="flex items-center gap-2">
                    <img src="/images/trypost/logo-light.png" alt="TryPost" class="dark:hidden h-7 w-auto" />
                    <img src="/images/trypost/logo-dark.png" alt="TryPost" class="hidden dark:block h-7 w-auto" />
                </Link>
                <nav class="flex items-center gap-4">
                    <Link
                        :href="home()"
                        class="text-sm text-muted-foreground hover:text-foreground transition-colors"
                    >
                        Home
                    </Link>
                </nav>
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
                <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                    <p class="text-sm text-muted-foreground">
                        &copy; {{ new Date().getFullYear() }} TryPost. All rights reserved.
                    </p>
                    <nav class="flex items-center gap-6">
                        <Link
                            href="/privacy"
                            class="text-sm text-muted-foreground hover:text-foreground transition-colors"
                        >
                            Privacy Policy
                        </Link>
                        <Link
                            href="/terms"
                            class="text-sm text-muted-foreground hover:text-foreground transition-colors"
                        >
                            Terms of Service
                        </Link>
                    </nav>
                </div>
            </div>
        </footer>
    </div>
</template>
