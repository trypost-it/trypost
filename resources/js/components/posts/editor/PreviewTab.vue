<script setup lang="ts">
import PhoneMockup from '@/components/PhoneMockup.vue';
import { PlatformPreview } from '@/components/posts/previews';

interface MediaItem {
    id: string;
    path: string;
    url: string;
    type?: string;
    mime_type?: string;
    original_filename?: string;
}

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}

defineProps<{
    platform: string;
    content: string;
    media: MediaItem[];
    socialAccount: SocialAccount | null;
    contentType: string | null;
}>();
</script>

<template>
    <div class="flex justify-center py-8 px-4 bg-muted/30 min-h-full">
        <PhoneMockup>
            <PlatformPreview :platform="platform" :content="content" :media="media" :social-account="socialAccount" :content-type="contentType" />
        </PhoneMockup>
    </div>
</template>
