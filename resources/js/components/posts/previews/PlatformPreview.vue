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
    type: string;
    original_filename: string;
}

interface ContentTypeOption {
    value: string;
    label: string;
    description: string;
}

interface Props {
    platform: string;
    socialAccount: SocialAccount;
    content: string;
    media: MediaItem[];
    contentType?: string;
    contentTypeOptions?: ContentTypeOption[];
    meta?: Record<string, any>;
    platformData?: Record<string, any>;
    charCount: number;
    maxLength: number;
    isValid: boolean;
    validationMessage: string;
    isUploading?: boolean;
    readonly?: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:content': [value: string];
    'update:contentType': [value: string];
    'update:meta': [value: Record<string, any>];
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

const handleContentUpdate = (value: string) => {
    emit('update:content', value);
};

const handleContentTypeUpdate = (value: string) => {
    emit('update:contentType', value);
};

const handleMetaUpdate = (value: Record<string, any>) => {
    emit('update:meta', value);
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
        :content-type-options="contentTypeOptions"
        :meta="meta"
        :platform-data="platformData"
        :char-count="charCount"
        :max-length="maxLength"
        :is-valid="isValid"
        :validation-message="validationMessage"
        :is-uploading="isUploading"
        :readonly="readonly"
        @update:content="handleContentUpdate"
        @update:content-type="handleContentTypeUpdate"
        @update:meta="handleMetaUpdate"
        @upload="handleUpload"
        @remove-media="handleRemoveMedia"
    />
</template>
