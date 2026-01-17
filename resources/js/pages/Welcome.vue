<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, Clock, Share2, Sparkles, CheckCircle } from 'lucide-vue-next';

import { calendar, login, privacy, register, terms } from '@/routes';
import { Button } from '@/components/ui/button';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const features = [
    {
        icon: CalendarDays,
        title: 'Visual Calendar',
        description: 'Plan and visualize your content with an intuitive calendar interface.',
    },
    {
        icon: Clock,
        title: 'Smart Scheduling',
        description: 'Schedule posts for the perfect time across all your social platforms.',
    },
    {
        icon: Share2,
        title: 'Multi-Platform',
        description: 'Publish to LinkedIn, X, TikTok, YouTube, Facebook, Instagram, and Threads.',
    },
    {
        icon: Sparkles,
        title: 'Workspace Collaboration',
        description: 'Invite team members and collaborate on your social media strategy.',
    },
];

const platforms = [
    { name: 'LinkedIn', logo: '/images/accounts/linkedin.png' },
    { name: 'X', logo: '/images/accounts/x.png' },
    { name: 'TikTok', logo: '/images/accounts/tiktok.png' },
    { name: 'YouTube', logo: '/images/accounts/youtube.png' },
    { name: 'Facebook', logo: '/images/accounts/facebook.png' },
    { name: 'Instagram', logo: '/images/accounts/instagram.png' },
    { name: 'Threads', logo: '/images/accounts/threads.png' },
];
</script>

<template>
    <Head title="Social Media Scheduling Made Simple">
        <meta name="description" content="Schedule and publish your social media content to multiple platforms with TryPost. Support for LinkedIn, X, TikTok, YouTube, Facebook, Instagram, and Threads." />
    </Head>

    <div class="min-h-svh bg-background">
        <!-- Header -->
        <header class="border-b">
            <div class="mx-auto max-w-6xl px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <img src="/images/trypost/logo-light.png" alt="TryPost" class="dark:hidden h-7 w-auto" />
                    <img src="/images/trypost/logo-dark.png" alt="TryPost" class="hidden dark:block h-7 w-auto" />
                </div>
                <nav class="flex items-center gap-3">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="calendar.url()"
                        class="text-sm text-muted-foreground hover:text-foreground transition-colors"
                    >
                        Calendar
                    </Link>
                    <template v-else>
                        <Link :href="login()">
                            <Button variant="ghost" size="sm">Log in</Button>
                        </Link>
                        <Link v-if="canRegister" :href="register()">
                            <Button size="sm">Get Started</Button>
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="py-20 md:py-32">
            <div class="mx-auto max-w-6xl px-6 text-center">
                <h1 class="text-4xl md:text-6xl font-bold tracking-tight mb-6">
                    Social Media Scheduling<br />
                    <span class="text-primary">Made Simple</span>
                </h1>
                <p class="text-lg md:text-xl text-muted-foreground max-w-2xl mx-auto mb-8">
                    Plan, schedule, and publish your content across all major social platforms from one place.
                    Save time and grow your audience.
                </p>
                <div class="flex items-center justify-center gap-4">
                    <Link v-if="canRegister" :href="register()">
                        <Button size="lg">Start Free Trial</Button>
                    </Link>
                    <Link :href="login()">
                        <Button variant="outline" size="lg">Log in</Button>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Platforms Section -->
        <section class="py-16 bg-muted/30">
            <div class="mx-auto max-w-6xl px-6">
                <p class="text-center text-sm text-muted-foreground mb-8">
                    Publish to all your favorite platforms
                </p>
                <div class="flex items-center justify-center gap-8 flex-wrap">
                    <div
                        v-for="platform in platforms"
                        :key="platform.name"
                        class="flex items-center gap-2 text-muted-foreground"
                    >
                        <img :src="platform.logo" :alt="platform.name" class="h-8 w-8" />
                        <span class="text-sm font-medium">{{ platform.name }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="py-20">
            <div class="mx-auto max-w-6xl px-6">
                <h2 class="text-3xl font-bold text-center mb-12">
                    Everything you need to manage your social presence
                </h2>
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <div
                        v-for="feature in features"
                        :key="feature.title"
                        class="p-6 rounded-lg border bg-card"
                    >
                        <component :is="feature.icon" class="h-10 w-10 text-primary mb-4" />
                        <h3 class="font-semibold mb-2">{{ feature.title }}</h3>
                        <p class="text-sm text-muted-foreground">{{ feature.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Benefits Section -->
        <section class="py-20 bg-muted/30">
            <div class="mx-auto max-w-6xl px-6">
                <div class="grid md:grid-cols-2 gap-12 items-center">
                    <div>
                        <h2 class="text-3xl font-bold mb-6">
                            Focus on creating, not scheduling
                        </h2>
                        <ul class="space-y-4">
                            <li class="flex items-start gap-3">
                                <CheckCircle class="h-5 w-5 text-green-500 mt-0.5 shrink-0" />
                                <span>Schedule weeks of content in minutes</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <CheckCircle class="h-5 w-5 text-green-500 mt-0.5 shrink-0" />
                                <span>Customize content for each platform</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <CheckCircle class="h-5 w-5 text-green-500 mt-0.5 shrink-0" />
                                <span>Upload images and videos with ease</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <CheckCircle class="h-5 w-5 text-green-500 mt-0.5 shrink-0" />
                                <span>Collaborate with your team</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <CheckCircle class="h-5 w-5 text-green-500 mt-0.5 shrink-0" />
                                <span>Track your publishing history</span>
                            </li>
                        </ul>
                    </div>
                    <div class="bg-card border rounded-lg p-8 text-center">
                        <p class="text-4xl font-bold text-primary mb-2">$20</p>
                        <p class="text-muted-foreground mb-6">per workspace / month</p>
                        <Link v-if="canRegister" :href="register()">
                            <Button class="w-full" size="lg">Start Free Trial</Button>
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20">
            <div class="mx-auto max-w-6xl px-6 text-center">
                <h2 class="text-3xl font-bold mb-4">Ready to streamline your social media?</h2>
                <p class="text-muted-foreground mb-8">
                    Join thousands of creators and businesses who trust TryPost.
                </p>
                <Link v-if="canRegister" :href="register()">
                    <Button size="lg">Get Started Now</Button>
                </Link>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t py-12">
            <div class="mx-auto max-w-6xl px-6">
                <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center gap-2">
                        <img src="/images/trypost/logo-light.png" alt="TryPost" class="dark:hidden h-6 w-auto" />
                        <img src="/images/trypost/logo-dark.png" alt="TryPost" class="hidden dark:block h-6 w-auto" />
                    </div>
                    <nav class="flex items-center gap-6">
                        <Link
                            :href="privacy.url()"
                            class="text-sm text-muted-foreground hover:text-foreground transition-colors"
                        >
                            Privacy Policy
                        </Link>
                        <Link
                            :href="terms.url()"
                            class="text-sm text-muted-foreground hover:text-foreground transition-colors"
                        >
                            Terms of Service
                        </Link>
                    </nav>
                    <p class="text-sm text-muted-foreground">
                        &copy; {{ new Date().getFullYear() }} TryPost. All rights reserved.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
