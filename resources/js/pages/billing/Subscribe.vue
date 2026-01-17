<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Sparkles, Calendar, Users, ImageIcon, Video, Clock, BarChart3 } from 'lucide-vue-next';
import { ref } from 'vue';

import { Button } from '@/components/ui/button';
import { checkout } from '@/routes/billing';

interface Props {
    trialDays: number;
}

defineProps<Props>();

const processing = ref(false);

function subscribe() {
    processing.value = true;
    router.post(checkout.url());
}

const platforms = [
    { name: 'LinkedIn Profile', icon: '/images/accounts/linkedin.png' },
    { name: 'LinkedIn Page', icon: '/images/accounts/linkedin.png' },
    { name: 'X (Twitter)', icon: '/images/accounts/x.png' },
    { name: 'TikTok', icon: '/images/accounts/tiktok.png' },
    { name: 'YouTube', icon: '/images/accounts/youtube.png' },
    { name: 'Instagram', icon: '/images/accounts/instagram.png' },
    { name: 'Facebook', icon: '/images/accounts/facebook.png' },
    { name: 'Threads', icon: '/images/accounts/threads.png' },
];

const features = [
    { icon: Calendar, title: 'Visual Calendar', description: 'Plan and schedule your content with an intuitive drag-and-drop calendar' },
    { icon: Clock, title: 'Unlimited Scheduling', description: 'Schedule as many posts as you want, whenever you want' },
    { icon: ImageIcon, title: 'Images & Carousels', description: 'Share single images or create engaging carousel posts' },
    { icon: Video, title: 'Video Publishing', description: 'Upload and publish videos across all your social accounts' },
    { icon: Users, title: 'Team Collaboration', description: 'Invite your team members and work together seamlessly' },
    { icon: BarChart3, title: 'Analytics', description: 'Track your post performance and engagement metrics' },
];
</script>

<template>
    <Head title="Start your free trial" />

    <div class="min-h-screen bg-gradient-to-b from-background via-background to-muted/30">
        <div class="container mx-auto px-4 py-12 max-w-4xl">
            <!-- Header -->
            <div class="text-center mb-12">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-primary/10 mb-6">
                    <Sparkles class="w-10 h-10 text-primary" />
                </div>
                <h1 class="text-4xl font-bold tracking-tight mb-3">Welcome to TryPost!</h1>
                <p class="text-xl text-muted-foreground">
                    Start your free {{ trialDays }}-day trial and take control of your social media
                </p>
            </div>

            <!-- Platforms -->
            <div class="mb-12">
                <h2 class="text-center text-sm font-medium text-muted-foreground uppercase tracking-wider mb-6">
                    Connect all your accounts
                </h2>
                <div class="flex flex-wrap justify-center gap-3">
                    <div
                        v-for="platform in platforms"
                        :key="platform.name"
                        class="flex items-center gap-2.5 px-4 py-2.5 rounded-full bg-card border shadow-sm hover:shadow-md transition-shadow"
                    >
                        <img :src="platform.icon" :alt="platform.name" class="w-5 h-5 rounded-full object-cover" />
                        <span class="text-sm font-medium">{{ platform.name }}</span>
                    </div>
                </div>
            </div>

            <!-- Features Grid -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="p-5 rounded-xl bg-card border hover:border-primary/50 hover:shadow-lg transition-all"
                >
                    <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center mb-3">
                        <component :is="feature.icon" class="w-5 h-5 text-primary" />
                    </div>
                    <h3 class="font-semibold mb-1">{{ feature.title }}</h3>
                    <p class="text-sm text-muted-foreground">{{ feature.description }}</p>
                </div>
            </div>

            <!-- CTA -->
            <div class="text-center">
                <Button @click="subscribe" :disabled="processing" size="lg" class="px-8">
                    Start my free trial
                </Button>
            </div>

        </div>
    </div>
</template>
