<script setup lang="ts">
import { computed } from 'vue';

import BlueskyPreview from './BlueskyPreview.vue';
import FacebookPreview from './FacebookPreview.vue';
import InstagramPreview from './InstagramPreview.vue';
import LinkedInPreview from './LinkedInPreview.vue';
import MastodonPreview from './MastodonPreview.vue';
import PinterestPreview from './PinterestPreview.vue';
import ThreadsPreview from './ThreadsPreview.vue';
import TikTokPreview from './TikTokPreview.vue';
import XPreview from './XPreview.vue';
import YouTubePreview from './YouTubePreview.vue';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string;
    avatar_url: string | null;
}

interface MediaItem {
    id: string;
    url: string;
    type?: string;
    mime_type?: string;
    original_filename?: string;
}

interface Props {
    platform: string;
    socialAccount: SocialAccount | null | undefined;
    content: string;
    media: MediaItem[];
    contentType?: string;
    meta?: Record<string, any>;
}

const props = defineProps<Props>();

const resolvedSocialAccount = computed((): SocialAccount => props.socialAccount ?? {
    id: '',
    platform: props.platform,
    display_name: '',
    username: '',
    avatar_url: null,
});

const previewComponent = computed(() => {
    switch (props.platform) {
        case 'linkedin':
        case 'linkedin-page':
            return LinkedInPreview;
        case 'x':
            return XPreview;
        case 'facebook':
            return FacebookPreview;
        case 'instagram':
        case 'instagram-facebook':
            return InstagramPreview;
        case 'threads':
            return ThreadsPreview;
        case 'tiktok':
            return TikTokPreview;
        case 'youtube':
            return YouTubePreview;
        case 'pinterest':
            return PinterestPreview;
        case 'bluesky':
            return BlueskyPreview;
        case 'mastodon':
            return MastodonPreview;
        default:
            return LinkedInPreview;
    }
});
</script>

<template>
    <component
        :is="previewComponent"
        :social-account="resolvedSocialAccount"
        :content="content"
        :media="media"
        :content-type="contentType"
        :meta="meta"
    />
</template>
