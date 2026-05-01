<script setup lang="ts">
import {
    IconArrowBackUp,
    IconEdit,
    IconLoader2,
    IconMoodSmile,
    IconSend,
    IconTrash,
    IconX,
} from '@tabler/icons-vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

import CommentBody from '@/components/CommentBody.vue';
import MentionTextarea from '@/components/MentionTextarea.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import date from '@/date';
import dayjs from '@/dayjs';
import {
    index as fetchComments,
    store as storeComment,
    update as updateComment,
    destroy as destroyComment,
    react as reactComment,
} from '@/routes/app/posts/comments';

interface User {
    id: string;
    name: string;
    avatar_url?: string | null;
    profile_photo_url?: string | null;
}

interface Reaction {
    user_id: string;
    emoji: string;
}

interface Comment {
    id: string;
    body: string;
    user_id: string;
    parent_id: string | null;
    reactions: Reaction[];
    created_at: string;
    updated_at: string;
    user: User;
    replies?: Comment[];
}

interface PaginatedResponse {
    data: Comment[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    mentioned_users?: Record<string, string>;
}

const props = defineProps<{
    postId: string;
    currentUserId: string;
    highlightCommentId?: string | null;
}>();

const EMOJIS = ['👍', '❤️', '😂', '🎉', '🔥', '👏', '😍', '🤔', '👀', '💯'];

const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const comments = ref<Comment[]>([]);
const currentPage = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const sending = ref(false);

const newBody = ref('');
const replyingTo = ref<Comment | null>(null);
const editingComment = ref<Comment | null>(null);
const editBody = ref('');
const hoveredCommentId = ref<string | null>(null);
const emojiPickerCommentId = ref<string | null>(null);

const scrollContainer = ref<HTMLDivElement | null>(null);
const textareaRef = ref<InstanceType<typeof MentionTextarea> | null>(null);

const hasOlderComments = computed(() => currentPage.value < lastPage.value);

interface DayGroup {
    label: string;
    comments: Comment[];
}

const commentsByDay = computed((): DayGroup[] => {
    const groups: Map<string, Comment[]> = new Map();

    for (const comment of comments.value) {
        const day = dayjs.utc(comment.created_at).local().format('YYYY-MM-DD');
        if (!groups.has(day)) groups.set(day, []);
        groups.get(day)!.push(comment);
    }

    const today = dayjs().format('YYYY-MM-DD');
    const yesterday = dayjs().subtract(1, 'day').format('YYYY-MM-DD');

    return Array.from(groups.entries()).map(([day, items]) => ({
        label: day === today ? 'Today' : day === yesterday ? 'Yesterday' : dayjs(day).format('MMM D, YYYY'),
        comments: items,
    }));
});

const getInitials = (name: string): string => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

const getAvatarUrl = (user: User): string | null => {
    return user.avatar_url || user.profile_photo_url || null;
};

const groupedReactions = (reactions: Reaction[]): { emoji: string; count: number; hasReacted: boolean }[] => {
    if (!reactions || reactions.length === 0) return [];
    const map = new Map<string, { count: number; hasReacted: boolean }>();
    for (const r of reactions) {
        const existing = map.get(r.emoji) || { count: 0, hasReacted: false };
        existing.count++;
        if (r.user_id === props.currentUserId) existing.hasReacted = true;
        map.set(r.emoji, existing);
    }
    return Array.from(map.entries()).map(([emoji, data]) => ({ emoji, ...data }));
};

const loadComments = async (page = 1) => {
    loading.value = true;
    try {
        const url = fetchComments.url(props.postId, { query: { page } });
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!response.ok) return;
        const data: PaginatedResponse = await response.json();
        currentPage.value = data.current_page;
        lastPage.value = data.last_page;

        if (data.mentioned_users) {
            memberNames.value = { ...memberNames.value, ...data.mentioned_users };
        }

        if (page === 1) {
            // Reverse so newest is at bottom
            comments.value = [...data.data].reverse();
            await nextTick();
            scrollToBottom();
        } else {
            // Prepend older comments at top (also reversed)
            const older = [...data.data].reverse();
            comments.value = [...older, ...comments.value];
        }
    } finally {
        loading.value = false;
    }
};

const loadOlderComments = () => {
    if (hasOlderComments.value && !loading.value) {
        loadComments(currentPage.value + 1);
    }
};

const scrollToBottom = () => {
    if (scrollContainer.value) {
        scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight;
    }
};

const sendComment = async () => {
    const body = newBody.value.trim();
    if (!body || sending.value) return;

    sending.value = true;
    try {
        const payload: Record<string, string> = { body };
        if (replyingTo.value) {
            payload.parent_id = replyingTo.value.id;
        }

        const response = await fetch(storeComment.url(props.postId), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        if (!response.ok) return;

        const created: Comment = await response.json();

        if (created.parent_id) {
            // Add reply to parent
            const parent = comments.value.find((c) => c.id === created.parent_id);
            if (parent) {
                if (!parent.replies) parent.replies = [];
                parent.replies.push(created);
            }
        } else {
            // Add to bottom (newest)
            created.replies = [];
            comments.value.push(created);
        }

        newBody.value = '';
        replyingTo.value = null;
        await nextTick();
        scrollToBottom();
    } finally {
        sending.value = false;
    }
};

const startReply = (comment: Comment) => {
    replyingTo.value = comment;
    editingComment.value = null;
    nextTick(() => {
        const el = textareaRef.value?.$el as HTMLTextAreaElement | undefined;
        el?.focus();
    });
};

const cancelReply = () => {
    replyingTo.value = null;
};

const startEdit = (comment: Comment) => {
    editingComment.value = comment;
    editBody.value = comment.body;
    replyingTo.value = null;
};

const cancelEdit = () => {
    editingComment.value = null;
    editBody.value = '';
};

const saveEdit = async () => {
    if (!editingComment.value || !editBody.value.trim()) return;

    const comment = editingComment.value;
    try {
        const response = await fetch(
            updateComment.url({ post: props.postId, comment: comment.id }),
            {
                method: 'PUT',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ body: editBody.value.trim() }),
            },
        );

        if (!response.ok) return;

        const updated: Comment = await response.json();

        // Update in top-level or in replies
        const topIndex = comments.value.findIndex((c) => c.id === comment.id);
        if (topIndex !== -1) {
            comments.value[topIndex].body = updated.body;
            comments.value[topIndex].updated_at = updated.updated_at;
        } else {
            for (const parent of comments.value) {
                const replyIndex = parent.replies?.findIndex((r) => r.id === comment.id) ?? -1;
                if (replyIndex !== -1 && parent.replies) {
                    parent.replies[replyIndex].body = updated.body;
                    parent.replies[replyIndex].updated_at = updated.updated_at;
                    break;
                }
            }
        }

        cancelEdit();
    } catch {
        // ignore
    }
};

const deleteComment = async (comment: Comment) => {
    try {
        const response = await fetch(
            destroyComment.url({ post: props.postId, comment: comment.id }),
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (!response.ok) return;

        // Remove from top-level
        const topIndex = comments.value.findIndex((c) => c.id === comment.id);
        if (topIndex !== -1) {
            comments.value.splice(topIndex, 1);
        } else {
            // Remove from replies
            for (const parent of comments.value) {
                const replyIndex = parent.replies?.findIndex((r) => r.id === comment.id) ?? -1;
                if (replyIndex !== -1 && parent.replies) {
                    parent.replies.splice(replyIndex, 1);
                    break;
                }
            }
        }
    } catch {
        // ignore
    }
};

const toggleReaction = async (comment: Comment, emoji: string) => {
    emojiPickerCommentId.value = null;
    try {
        const response = await fetch(
            reactComment.url({ post: props.postId, comment: comment.id }),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ emoji }),
            },
        );

        if (!response.ok) return;

        const updated: Comment = await response.json();

        // Update reactions in the right place
        const topIndex = comments.value.findIndex((c) => c.id === comment.id);
        if (topIndex !== -1) {
            comments.value[topIndex].reactions = updated.reactions;
        } else {
            for (const parent of comments.value) {
                const replyIndex = parent.replies?.findIndex((r) => r.id === comment.id) ?? -1;
                if (replyIndex !== -1 && parent.replies) {
                    parent.replies[replyIndex].reactions = updated.reactions;
                    break;
                }
            }
        }
    } catch {
        // ignore
    }
};

const showEmojiPicker = (commentId: string) => {
    emojiPickerCommentId.value = commentId;
};

const hideEmojiPicker = () => {
    emojiPickerCommentId.value = null;
};

const handleKeydown = (event: KeyboardEvent) => {
    if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
        event.preventDefault();
        sendComment();
    }
};

const handleEditKeydown = (event: KeyboardEvent) => {
    if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
        event.preventDefault();
        saveEdit();
    }
    if (event.key === 'Escape') {
        cancelEdit();
    }
};

const addCommentFromBroadcast = (comment: Comment) => {
    // Avoid duplicates
    if (comment.user_id === props.currentUserId) return;

    if (comment.parent_id) {
        const parent = comments.value.find((c) => c.id === comment.parent_id);
        if (parent) {
            const exists = parent.replies?.some((r) => r.id === comment.id);
            if (!exists) {
                if (!parent.replies) parent.replies = [];
                parent.replies.push(comment);
            }
        }
    } else {
        const exists = comments.value.some((c) => c.id === comment.id);
        if (!exists) {
            comment.replies = comment.replies || [];
            comments.value.push(comment);
            nextTick(() => scrollToBottom());
        }
    }
};

const memberNames = ref<Record<string, string>>({});

const registerMention = (member: { id: string; name: string }) => {
    memberNames.value = { ...memberNames.value, [member.id]: member.name };
};

const registerMentionedUsers = (users: Record<string, string>) => {
    memberNames.value = { ...memberNames.value, ...users };
};

defineExpose({ addCommentFromBroadcast, registerMentionedUsers });

const highlightedId = ref<string | null>(null);

const focusComment = async (commentId: string) => {
    await nextTick();
    const el = document.querySelector<HTMLElement>(`[data-comment-id="${commentId}"]`);
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    highlightedId.value = commentId;
    setTimeout(() => {
        if (highlightedId.value === commentId) highlightedId.value = null;
    }, 4000);
};

onMounted(async () => {
    await loadComments(1);
    if (props.highlightCommentId) {
        await focusComment(props.highlightCommentId);
    }
});

watch(
    () => props.highlightCommentId,
    (id) => {
        if (id) void focusComment(id);
    },
);

watch(() => props.postId, () => {
    comments.value = [];
    currentPage.value = 1;
    loadComments(1);
});
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Comment list (inverted scroll: newest at bottom) -->
        <div ref="scrollContainer" class="flex-1 overflow-y-auto">
            <!-- Load older button -->
            <div v-if="hasOlderComments" class="flex justify-center py-2">
                <Button variant="ghost" size="sm" :disabled="loading" @click="loadOlderComments">
                    <IconLoader2 v-if="loading" class="mr-1.5 h-3.5 w-3.5 animate-spin" />
                    {{ $t('comments.load_more') }}
                </Button>
            </div>

            <!-- Empty state -->
            <div v-if="!loading && comments.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                <p class="text-sm text-muted-foreground">{{ $t('comments.empty') }}</p>
            </div>

            <!-- Comments grouped by day -->
            <div class="px-2 py-1">
                <template v-for="group in commentsByDay" :key="group.label">
                    <div class="mt-2 mb-4 text-center text-xs text-muted-foreground">{{ group.label }}</div>

                <template v-for="comment in group.comments" :key="comment.id">
                    <!-- Top-level comment -->
                    <div
                        :data-comment-id="comment.id"
                        class="group relative rounded-lg py-1.5 px-2 transition-colors"
                        :class="highlightedId === comment.id ? 'bg-primary/10 ring-1 ring-primary/30' : 'hover:bg-muted/50'"
                        @mouseenter="hoveredCommentId = comment.id"
                        @mouseleave="hoveredCommentId = null; emojiPickerCommentId = null"
                    >
                        <!-- Editing mode -->
                        <div v-if="editingComment?.id === comment.id" class="space-y-2">
                            <MentionTextarea
                                v-model="editBody"
                                :member-names="memberNames"
                                class="min-h-[60px] resize-none text-sm"
                                @keydown="handleEditKeydown"
                                @mention="registerMention"
                            />
                            <div class="flex items-center gap-1.5">
                                <Button size="sm" variant="default" @click="saveEdit">{{ $t('comments.save') }}</Button>
                                <Button size="sm" variant="ghost" @click="cancelEdit">{{ $t('comments.cancel') }}</Button>
                            </div>
                        </div>

                        <!-- Display mode -->
                        <template v-else>
                            <div class="flex items-start gap-2">
                                <Avatar class="h-6 w-6 shrink-0">
                                    <AvatarImage v-if="getAvatarUrl(comment.user)" :src="getAvatarUrl(comment.user)!" />
                                    <AvatarFallback class="text-[10px]">{{ getInitials(comment.user.name) }}</AvatarFallback>
                                </Avatar>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline gap-1.5">
                                        <span class="text-xs font-medium">{{ comment.user.name }}</span>
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <span class="text-[10px] text-muted-foreground">{{ date.diffForHumans(comment.created_at) }}</span>
                                                </TooltipTrigger>
                                                <TooltipContent side="top">
                                                    <span class="text-xs">{{ date.formatDateTime(comment.created_at) }}</span>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                        <span v-if="comment.updated_at !== comment.created_at" class="text-[10px] text-muted-foreground italic">({{ $t('comments.edited') }})</span>
                                    </div>
                                    <CommentBody :body="comment.body" :members="memberNames" />

                                    <!-- Reactions -->
                                    <div v-if="groupedReactions(comment.reactions).length > 0" class="mt-1.5 flex flex-wrap gap-1">
                                        <button
                                            v-for="r in groupedReactions(comment.reactions)"
                                            :key="r.emoji"
                                            class="inline-flex items-center gap-0.5 rounded-full border px-1.5 py-0.5 text-xs transition-colors"
                                            :class="r.hasReacted ? 'border-primary/40 bg-primary/10' : 'border-border hover:border-primary/30'"
                                            @click="toggleReaction(comment, r.emoji)"
                                        >
                                            <span>{{ r.emoji }}</span>
                                            <span class="text-[10px] text-muted-foreground">{{ r.count }}</span>
                                        </button>
                                    </div>

                                </div>

                                <!-- Floating toolbar -->
                                <div
                                    v-if="hoveredCommentId === comment.id && editingComment?.id !== comment.id"
                                    class="absolute -top-3 right-2 z-50 flex items-center gap-0.5 rounded-md border bg-background px-1 py-0.5 shadow-sm"
                                >
                                    <TooltipProvider :delay-duration="200">
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <button class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground" @mouseenter="showEmojiPicker(comment.id)"><IconMoodSmile class="h-3.5 w-3.5" /></button>
                                            </TooltipTrigger>
                                            <TooltipContent side="top" class="text-xs">React</TooltipContent>
                                        </Tooltip>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <button class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground" @click="startReply(comment)"><IconArrowBackUp class="h-3.5 w-3.5" /></button>
                                            </TooltipTrigger>
                                            <TooltipContent side="top" class="text-xs">{{ $t('comments.reply') }}</TooltipContent>
                                        </Tooltip>
                                        <template v-if="comment.user_id === currentUserId">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <button class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground" @click="startEdit(comment)"><IconEdit class="h-3.5 w-3.5" /></button>
                                                </TooltipTrigger>
                                                <TooltipContent side="top" class="text-xs">{{ $t('comments.edit') }}</TooltipContent>
                                            </Tooltip>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <button class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-destructive" @click="deleteComment(comment)"><IconTrash class="h-3.5 w-3.5" /></button>
                                                </TooltipTrigger>
                                                <TooltipContent side="top" class="text-xs">{{ $t('comments.delete') }}</TooltipContent>
                                            </Tooltip>
                                        </template>
                                    </TooltipProvider>
                                </div>

                                <!-- Emoji picker -->
                                <div
                                    v-if="emojiPickerCommentId === comment.id"
                                    class="absolute -top-10 right-2 z-50 flex items-center gap-0.5 rounded-lg border bg-popover p-1.5 shadow-md"                                >
                                    <button v-for="emoji in EMOJIS" :key="emoji" class="rounded p-0.5 text-sm transition-transform hover:scale-125 hover:bg-muted" @click="toggleReaction(comment, emoji)">{{ emoji }}</button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Replies (1 level) -->
                    <template v-if="comment.replies && comment.replies.length > 0">
                        <div
                            v-for="reply in comment.replies"
                            :key="reply.id"
                            :data-comment-id="reply.id"
                            class="group relative ml-8 rounded-lg py-1.5 px-2 transition-colors"
                            :class="highlightedId === reply.id ? 'bg-primary/10 ring-1 ring-primary/30' : 'hover:bg-muted/50'"
                            @mouseenter="hoveredCommentId = reply.id"
                            @mouseleave="hoveredCommentId = null; emojiPickerCommentId = null"
                        >
                            <!-- Editing reply -->
                            <div v-if="editingComment?.id === reply.id" class="space-y-2">
                                <MentionTextarea
                                    v-model="editBody"
                                    :member-names="memberNames"
                                    class="min-h-[60px] resize-none text-sm"
                                    @keydown="handleEditKeydown"
                                    @mention="registerMention"
                                />
                                <div class="flex items-center gap-1.5">
                                    <Button size="sm" variant="default" @click="saveEdit">{{ $t('comments.save') }}</Button>
                                    <Button size="sm" variant="ghost" @click="cancelEdit">{{ $t('comments.cancel') }}</Button>
                                </div>
                            </div>

                            <!-- Display reply -->
                            <template v-else>
                                <div class="flex items-start gap-2">
                                    <Avatar class="h-5 w-5 shrink-0">
                                        <AvatarImage v-if="getAvatarUrl(reply.user)" :src="getAvatarUrl(reply.user)!" />
                                        <AvatarFallback class="text-[9px]">{{ getInitials(reply.user.name) }}</AvatarFallback>
                                    </Avatar>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-baseline gap-1.5">
                                            <span class="text-xs font-medium">{{ reply.user.name }}</span>
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <span class="text-[10px] text-muted-foreground">{{ date.diffForHumans(reply.created_at) }}</span>
                                                    </TooltipTrigger>
                                                    <TooltipContent side="top">
                                                        <span class="text-xs">{{ date.formatDateTime(reply.created_at) }}</span>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                            <span v-if="reply.updated_at !== reply.created_at" class="text-[10px] text-muted-foreground italic">({{ $t('comments.edited') }})</span>
                                        </div>
                                        <CommentBody :body="reply.body" :members="memberNames" />

                                        <!-- Reply reactions -->
                                        <div v-if="groupedReactions(reply.reactions).length > 0" class="mt-1.5 flex flex-wrap gap-1">
                                            <button
                                                v-for="r in groupedReactions(reply.reactions)"
                                                :key="r.emoji"
                                                class="inline-flex items-center gap-0.5 rounded-full border px-1.5 py-0.5 text-xs transition-colors"
                                                :class="r.hasReacted ? 'border-primary/40 bg-primary/10' : 'border-border hover:border-primary/30'"
                                                @click="toggleReaction(reply, r.emoji)"
                                            >
                                                <span>{{ r.emoji }}</span>
                                                <span class="text-[10px] text-muted-foreground">{{ r.count }}</span>
                                            </button>
                                        </div>

                                    </div>

                                    <!-- Reply floating toolbar -->
                                    <div
                                        v-if="hoveredCommentId === reply.id && editingComment?.id !== reply.id"
                                        class="absolute -top-3 right-2 z-50 flex items-center gap-0.5 rounded-md border bg-background px-1 py-0.5 shadow-sm"
                                    >
                                        <TooltipProvider :delay-duration="200">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <button class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground" @mouseenter="showEmojiPicker(reply.id)"><IconMoodSmile class="h-3.5 w-3.5" /></button>
                                                </TooltipTrigger>
                                                <TooltipContent side="top" class="text-xs">React</TooltipContent>
                                            </Tooltip>
                                            <template v-if="reply.user_id === currentUserId">
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <button class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground" @click="startEdit(reply)"><IconEdit class="h-3.5 w-3.5" /></button>
                                                    </TooltipTrigger>
                                                    <TooltipContent side="top" class="text-xs">{{ $t('comments.edit') }}</TooltipContent>
                                                </Tooltip>
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <button class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-destructive" @click="deleteComment(reply)"><IconTrash class="h-3.5 w-3.5" /></button>
                                                    </TooltipTrigger>
                                                    <TooltipContent side="top" class="text-xs">{{ $t('comments.delete') }}</TooltipContent>
                                                </Tooltip>
                                            </template>
                                        </TooltipProvider>
                                    </div>

                                    <!-- Reply emoji picker -->
                                    <div
                                        v-if="emojiPickerCommentId === reply.id"
                                        class="absolute -top-10 right-2 z-50 flex items-center gap-0.5 rounded-lg border bg-popover p-1.5 shadow-md"                                    >
                                        <button v-for="emoji in EMOJIS" :key="emoji" class="rounded p-0.5 text-sm transition-transform hover:scale-125 hover:bg-muted" @click="toggleReaction(reply, emoji)">{{ emoji }}</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </template>
                </template>
            </div>

            <!-- Loading spinner for initial load -->
            <div v-if="loading && comments.length === 0" class="flex items-center justify-center py-8">
                <IconLoader2 class="h-5 w-5 animate-spin text-muted-foreground" />
            </div>
        </div>

        <!-- Input area -->
        <div class="shrink-0 border-t p-2">
            <!-- Replying to indicator -->
            <div v-if="replyingTo" class="mb-1.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                <IconArrowBackUp class="h-3 w-3" />
                <span>{{ $t('comments.replying_to', { name: replyingTo.user.name }) }}</span>
                <button class="ml-auto rounded p-0.5 hover:bg-muted" @click="cancelReply">
                    <IconX class="h-3 w-3" />
                </button>
            </div>

            <div class="flex items-end gap-1.5">
                <MentionTextarea
                    ref="textareaRef"
                    v-model="newBody"
                    :member-names="memberNames"
                    :placeholder="replyingTo ? $t('comments.reply_placeholder') : $t('comments.placeholder')"
                    class="min-h-[36px] max-h-[120px] flex-1 resize-none text-sm"
                    :rows="1"
                    @keydown="handleKeydown"
                    @mention="registerMention"
                />
                <Button
                    size="icon"
                    variant="ghost"
                    class="h-9 w-9 shrink-0"
                    :disabled="!newBody.trim() || sending"
                    @click="sendComment"
                >
                    <IconLoader2 v-if="sending" class="h-4 w-4 animate-spin" />
                    <IconSend v-else class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
