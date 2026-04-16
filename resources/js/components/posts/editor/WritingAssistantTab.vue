<script setup lang="ts">
import { IconCheck, IconLoader2, IconPaperclip, IconPlus, IconSend, IconSparkles, IconX } from '@tabler/icons-vue';
import { nextTick, onMounted, ref } from 'vue';

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import date from '@/date';
import { index as fetchMessages, store as storeMessage } from '@/routes/app/posts/assistant';

interface Attachment {
    id: string;
    path: string;
    url: string;
    type: string;
    mime_type: string;
}

interface AiMessage {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    content_html?: string;
    attachments?: Attachment[];
    metadata?: {
        intent?: string;
        error?: boolean;
        limit_reached?: boolean;
    };
    created_at: string;
    user?: {
        id: string;
        name: string;
    };
}

const props = defineProps<{
    postId: string;
    workspaceId: string;
}>();

const emit = defineEmits<{
    'add-media': [payload: { id: string; path: string; url: string; type: string; mime_type: string }];
}>();

const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const messages = ref<AiMessage[]>([]);
const loading = ref(false);
const sending = ref(false);
const body = ref('');
const addedAttachmentIds = ref<Set<string>>(new Set());

const fileInput = ref<HTMLInputElement | null>(null);
const selectedImage = ref<File | null>(null);
const imagePreview = ref<string | null>(null);

const scrollContainer = ref<HTMLDivElement | null>(null);

const scrollToBottom = () => {
    if (scrollContainer.value) {
        scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight;
    }
};

const triggerFileInput = () => fileInput.value?.click();

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        selectedImage.value = file;
        imagePreview.value = URL.createObjectURL(file);
    }
    target.value = '';
};

const clearImage = () => {
    selectedImage.value = null;
    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value);
        imagePreview.value = null;
    }
};

const loadMessages = async () => {
    loading.value = true;
    try {
        const response = await fetch(fetchMessages.url(props.postId), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!response.ok) return;
        const data = await response.json();
        messages.value = data.messages ?? [];
        await nextTick();
        scrollToBottom();
    } finally {
        loading.value = false;
    }
};

const sendMessage = async () => {
    const text = body.value.trim();
    if (!text || sending.value) return;

    body.value = '';
    sending.value = true;

    // Optimistic: show user message immediately
    const tempUserMessage: AiMessage = {
        id: `temp-${Date.now()}`,
        role: 'user',
        content: text,
        attachments: imagePreview.value
            ? [{ id: 'temp', path: '', url: imagePreview.value, type: 'image', mime_type: 'image/jpeg' }]
            : undefined,
        created_at: new Date().toISOString(),
    };
    messages.value.push(tempUserMessage);

    const imageFile = selectedImage.value;
    clearImage();

    await nextTick();
    scrollToBottom();

    try {
        let fetchOptions: RequestInit;

        if (imageFile) {
            const formData = new FormData();
            formData.append('body', text);
            formData.append('image', imageFile);
            fetchOptions = {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            };
        } else {
            fetchOptions = {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ body: text }),
            };
        }

        const response = await fetch(storeMessage.url(props.postId), fetchOptions);

        if (!response.ok) {
            sending.value = false;
            return;
        }

        const data = await response.json();

        // Replace temp user message with real one
        const tempIdx = messages.value.findIndex((m) => m.id === tempUserMessage.id);
        if (tempIdx !== -1) {
            messages.value[tempIdx] = data.user_message;
        }

        // Add assistant response
        messages.value.push(data.assistant_message);

        await nextTick();
        scrollToBottom();
    } finally {
        sending.value = false;
    }
};

const handleKeydown = (event: KeyboardEvent) => {
    if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
        event.preventDefault();
        sendMessage();
    }
};

const addToPost = (attachment: Attachment) => {
    emit('add-media', {
        id: attachment.id,
        path: attachment.path,
        url: attachment.url,
        type: attachment.type,
        mime_type: attachment.mime_type,
    });
    addedAttachmentIds.value.add(attachment.id);
};

const isAdded = (attachmentId: string): boolean => {
    return addedAttachmentIds.value.has(attachmentId);
};

const isMedia = (attachment: Attachment): boolean => {
    return attachment.mime_type?.startsWith('audio/') || attachment.type === 'audio' || attachment.type === 'video' || attachment.type === 'image';
};

const isAudio = (attachment: Attachment): boolean => {
    return attachment.mime_type?.startsWith('audio/') || attachment.type === 'audio';
};

const isVideo = (attachment: Attachment): boolean => {
    return (attachment.mime_type?.startsWith('video/') || attachment.type === 'video') && !isAudio(attachment);
};

const isImage = (attachment: Attachment): boolean => {
    return attachment.mime_type?.startsWith('image/') || attachment.type === 'image';
};


onMounted(() => {
    loadMessages();
});
</script>

<template>
    <div class="flex h-full flex-col">
        <div ref="scrollContainer" class="flex-1 overflow-y-auto">
            <!-- Loading skeleton -->
            <div v-if="loading && messages.length === 0" class="space-y-4 px-3 py-4">
                <div class="flex justify-end gap-2">
                    <div class="max-w-[70%] space-y-1.5">
                        <Skeleton class="ml-auto h-10 w-48 rounded-lg" />
                        <Skeleton class="ml-auto h-3 w-16" />
                    </div>
                    <Skeleton class="h-6 w-6 shrink-0 rounded-full" />
                </div>
                <div class="flex justify-start gap-2">
                    <Skeleton class="h-6 w-6 shrink-0 rounded-full" />
                    <div class="max-w-[70%] space-y-1.5">
                        <Skeleton class="h-16 w-56 rounded-lg" />
                        <Skeleton class="h-3 w-16" />
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <div class="max-w-[70%] space-y-1.5">
                        <Skeleton class="ml-auto h-8 w-36 rounded-lg" />
                        <Skeleton class="ml-auto h-3 w-16" />
                    </div>
                    <Skeleton class="h-6 w-6 shrink-0 rounded-full" />
                </div>
            </div>

            <!-- Empty state -->
            <div v-else-if="messages.length === 0" class="flex flex-col items-center justify-center py-16 text-center px-6">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                    <IconSparkles class="h-6 w-6 text-muted-foreground" />
                </div>
                <p class="text-sm text-muted-foreground">{{ $t('assistant.empty') }}</p>
            </div>

            <!-- Messages -->
            <div v-else class="space-y-3 px-3 py-3">
                <template v-for="message in messages" :key="message.id">
                    <!-- User message -->
                    <div v-if="message.role === 'user'" class="flex justify-end gap-2">
                        <div class="max-w-[80%]">
                            <div class="rounded-2xl rounded-br-sm bg-primary px-3 py-2 text-primary-foreground">
                                <p class="whitespace-pre-wrap text-sm">{{ message.content }}</p>
                                <template v-if="message.attachments && message.attachments.length > 0">
                                    <img
                                        v-for="att in message.attachments"
                                        :key="att.id"
                                        :src="att.url"
                                        class="mt-1.5 w-full rounded-lg"
                                        loading="lazy"
                                    />
                                </template>
                            </div>
                            <p class="mt-0.5 text-right text-[10px] text-muted-foreground">{{ date.diffForHumans(message.created_at) }}</p>
                        </div>
                    </div>

                    <!-- Assistant message -->
                    <div v-else class="flex justify-start gap-2">
                        <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10">
                            <IconSparkles class="h-3 w-3 text-primary" />
                        </div>
                        <div class="max-w-[80%]">
                            <div
                                :class="[
                                    'rounded-2xl rounded-bl-sm px-3 py-2',
                                    message.metadata?.error ? 'bg-destructive/10 text-destructive' : 'bg-muted',
                                ]"
                            >
                                <div
                                    v-if="message.content_html"
                                    class="prose prose-sm dark:prose-invert max-w-none text-sm [&>p:last-child]:mb-0 [&>p:first-child]:mt-0"
                                    v-html="message.content_html"
                                />
                                <p v-else class="whitespace-pre-wrap text-sm">{{ message.content }}</p>

                                <template v-if="message.attachments && message.attachments.length > 0">
                                    <div v-for="attachment in message.attachments" :key="attachment.id" class="mt-2.5 space-y-2">
                                        <img
                                            v-if="isImage(attachment)"
                                            :src="attachment.url"
                                            :alt="'AI generated image'"
                                            class="w-full rounded-lg"
                                            loading="lazy"
                                        />

                                        <audio
                                            v-else-if="isAudio(attachment)"
                                            :src="attachment.url"
                                            controls
                                            class="w-full"
                                        />

                                        <video
                                            v-else-if="isVideo(attachment)"
                                            :src="attachment.url"
                                            controls
                                            class="w-full rounded-lg"
                                        />

                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="w-full"
                                            :disabled="isAdded(attachment.id)"
                                            @click="addToPost(attachment)"
                                        >
                                            <IconCheck v-if="isAdded(attachment.id)" class="mr-1.5 h-3.5 w-3.5" />
                                            <IconPlus v-else class="mr-1.5 h-3.5 w-3.5" />
                                            {{ isAdded(attachment.id) ? $t('assistant.added') : $t('assistant.add_to_post') }}
                                        </Button>
                                    </div>
                                </template>
                            </div>
                            <p class="mt-0.5 text-[10px] text-muted-foreground">{{ date.diffForHumans(message.created_at) }}</p>
                        </div>
                    </div>
                </template>

                <!-- Thinking indicator -->
                <div v-if="sending" class="flex justify-start gap-2">
                    <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10">
                        <IconSparkles class="h-3 w-3 animate-pulse text-primary" />
                    </div>
                    <div class="rounded-2xl rounded-bl-sm bg-muted px-4 py-2.5">
                        <div class="flex items-center gap-1">
                            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-muted-foreground/60 [animation-delay:0ms]" />
                            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-muted-foreground/60 [animation-delay:150ms]" />
                            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-muted-foreground/60 [animation-delay:300ms]" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Input -->
        <div class="shrink-0 border-t p-3">
            <!-- Image preview -->
            <div v-if="imagePreview" class="mb-2 flex items-center gap-2">
                <img :src="imagePreview" class="h-16 w-16 rounded-lg object-cover" />
                <button type="button" class="text-xs text-muted-foreground hover:text-destructive" @click="clearImage">
                    <IconX class="h-4 w-4" />
                </button>
            </div>

            <div class="flex items-end gap-2">
                <button type="button" class="mb-1 text-muted-foreground hover:text-foreground" @click="triggerFileInput">
                    <IconPaperclip class="h-5 w-5" />
                </button>
                <input
                    ref="fileInput"
                    type="file"
                    class="hidden"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    @change="handleFileSelect"
                />
                <Textarea
                    v-model="body"
                    :placeholder="$t('assistant.placeholder')"
                    class="min-h-[40px] max-h-[120px] flex-1 resize-none text-sm"
                    rows="1"
                    :disabled="sending"
                    @keydown="handleKeydown"
                />
                <Button
                    size="icon"
                    class="h-10 w-10 shrink-0"
                    :disabled="!body.trim() || sending"
                    @click="sendMessage"
                >
                    <IconLoader2 v-if="sending" class="h-4 w-4 animate-spin" />
                    <IconSend v-else class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
