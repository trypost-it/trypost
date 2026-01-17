<script setup lang="ts">
import { computed } from 'vue';
import LinkedInPreview from './LinkedInPreview.vue';
import XPreview from './XPreview.vue';
import FacebookPreview from './FacebookPreview.vue';
import InstagramPreview from './InstagramPreview.vue';
import ThreadsPreview from './ThreadsPreview.vue';
import TikTokPreview from './TikTokPreview.vue';
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
    type: string;
    original_filename: string;
}

interface Props {
    platform: string;
    socialAccount: SocialAccount;
    content: string;
    media: MediaItem[];
    contentType?: string;
    charCount: number;
    maxLength: number;
    isValid: boolean;
    validationMessage: string;
    isUploading?: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:content': [value: string];
    'upload': [event: Event];
    'remove-media': [mediaId: string];
}>();

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
            return InstagramPreview;
        case 'threads':
            return ThreadsPreview;
        case 'tiktok':
            return TikTokPreview;
        case 'youtube':
            return YouTubePreview;
        default:
            return LinkedInPreview;
    }
});

const handleContentUpdate = (value: string) => {
    emit('update:content', value);
};

const handleUpload = (event: Event) => {
    emit('upload', event);
};

const handleRemoveMedia = (mediaId: string) => {
    emit('remove-media', mediaId);
};
</script>

<template>
    <component
        :is="previewComponent"
        :social-account="socialAccount"
        :content="content"
        :media="media"
        :content-type="contentType"
        :char-count="charCount"
        :max-length="maxLength"
        :is-valid="isValid"
        :validation-message="validationMessage"
        :is-uploading="isUploading"
        @update:content="handleContentUpdate"
        @upload="handleUpload"
        @remove-media="handleRemoveMedia"
    />
</template>
