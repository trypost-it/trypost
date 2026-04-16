<script setup lang="ts">
import { IconLoader2, IconPlus, IconSend, IconSparkles } from '@tabler/icons-vue';
import { nextTick, onMounted, ref } from 'vue';

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
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
    attachments?: Attachment[];
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

const scrollContainer = ref<HTMLDivElement | null>(null);

const scrollToBottom = () => {
    if (scrollContainer.value) {
        scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight;
    }
};

const loadMessages = async () => {
    loading.value = true;
    try {
        const response = await fetch(fetchMessages.url(props.postId), {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
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

    sending.value = true;
    body.value = '';

    try {
        const response = await fetch(storeMessage.url(props.postId), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ body: text }),
        });

        if (!response.ok) return;

        const data = await response.json();
        messages.value.push(data.user_message);
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

onMounted(() => {
    loadMessages();
});
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Message list -->
        <div ref="scrollContainer" class="flex-1 overflow-y-auto">
            <!-- Loading spinner -->
            <div v-if="loading && messages.length === 0" class="flex items-center justify-center py-8">
                <IconLoader2 class="h-5 w-5 animate-spin text-muted-foreground" />
            </div>

            <!-- Empty state -->
            <div v-else-if="messages.length === 0" class="flex flex-col items-center justify-center py-12 text-center px-4">
                <IconSparkles class="mb-3 h-8 w-8 text-muted-foreground/50" />
                <p class="text-sm text-muted-foreground">{{ $t('assistant.empty') }}</p>
            </div>

            <!-- Messages -->
            <div v-else class="space-y-3 px-3 py-3">
                <template v-for="message in messages" :key="message.id">
                    <!-- User message -->
                    <div v-if="message.role === 'user'" class="flex justify-end gap-2">
                        <div class="max-w-[85%]">
                            <div class="rounded-lg bg-primary/10 px-3 py-2">
                                <p class="whitespace-pre-wrap text-sm">{{ message.content }}</p>
                            </div>
                            <p class="mt-0.5 text-right text-[10px] text-muted-foreground">{{ date.diffForHumans(message.created_at) }}</p>
                        </div>
                        <Avatar class="h-6 w-6 shrink-0">
                            <AvatarFallback class="text-[10px]">{{ message.user?.name?.charAt(0)?.toUpperCase() ?? 'U' }}</AvatarFallback>
                        </Avatar>
                    </div>

                    <!-- Assistant message -->
                    <div v-else class="flex justify-start gap-2">
                        <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-muted">
                            <IconSparkles class="h-3.5 w-3.5 text-muted-foreground" />
                        </div>
                        <div class="max-w-[85%]">
                            <div class="rounded-lg bg-muted px-3 py-2">
                                <p class="whitespace-pre-wrap text-sm">{{ message.content }}</p>

                                <!-- Attachments -->
                                <template v-if="message.attachments && message.attachments.length > 0">
                                    <div v-for="attachment in message.attachments" :key="attachment.id" class="mt-2 space-y-1.5">
                                        <!-- Image -->
                                        <img
                                            v-if="attachment.type === 'image'"
                                            :src="attachment.url"
                                            class="max-w-full rounded-md"
                                            loading="lazy"
                                        />

                                        <!-- Audio -->
                                        <audio
                                            v-else-if="attachment.type === 'audio'"
                                            :src="attachment.url"
                                            controls
                                            class="w-full"
                                        />

                                        <!-- Video -->
                                        <video
                                            v-else-if="attachment.type === 'video'"
                                            :src="attachment.url"
                                            controls
                                            class="max-w-full rounded-md"
                                        />

                                        <!-- Add to post button -->
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="mt-1"
                                            :disabled="isAdded(attachment.id)"
                                            @click="addToPost(attachment)"
                                        >
                                            <IconPlus v-if="!isAdded(attachment.id)" class="mr-1 h-3.5 w-3.5" />
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
                    <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-muted">
                        <IconSparkles class="h-3.5 w-3.5 text-muted-foreground" />
                    </div>
                    <div class="rounded-lg bg-muted px-3 py-2">
                        <span class="text-sm text-muted-foreground">{{ $t('assistant.thinking') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Input area -->
        <div class="shrink-0 border-t p-2">
            <div class="flex items-end gap-1.5">
                <Textarea
                    v-model="body"
                    :placeholder="$t('assistant.placeholder')"
                    class="min-h-[36px] max-h-[120px] flex-1 resize-none text-sm"
                    rows="1"
                    @keydown="handleKeydown"
                />
                <Button
                    size="icon"
                    variant="ghost"
                    class="h-9 w-9 shrink-0"
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
