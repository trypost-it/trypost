<script setup lang="ts">
import { IconCheck, IconLoader2, IconWriting } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { review as reviewPostAi } from '@/routes/app/posts/ai';

interface Suggestion {
    original: string;
    suggestion: string;
    reason: string;
}

const props = defineProps<{
    postId: string;
    content: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    (e: 'apply', original: string, suggestion: string): void;
}>();

const status = ref<'idle' | 'loading' | 'completed' | 'failed'>('idle');
const suggestions = ref<Suggestion[]>([]);
const appliedSet = ref<Set<number>>(new Set());
const errorMessage = ref<string | null>(null);

const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const startReview = async () => {
    status.value = 'loading';
    suggestions.value = [];
    appliedSet.value = new Set();
    errorMessage.value = null;

    try {
        const response = await fetch(reviewPostAi.url(props.postId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ content: props.content }),
        });
        if (!response.ok) {
            status.value = 'failed';
            errorMessage.value = 'Could not review';
            return;
        }
        const data = await response.json();
        suggestions.value = data.suggestions ?? [];
        status.value = 'completed';
    } catch {
        status.value = 'failed';
        errorMessage.value = 'Network error';
    }
};

const applySuggestion = (index: number, s: Suggestion) => {
    if (appliedSet.value.has(index)) return;
    emit('apply', s.original, s.suggestion);
    const next = new Set(appliedSet.value);
    next.add(index);
    appliedSet.value = next;
};

const noIssues = computed(() => status.value === 'completed' && suggestions.value.length === 0);

watch(open, (isOpen) => {
    if (isOpen) startReview();
    else {
        status.value = 'idle';
        suggestions.value = [];
        appliedSet.value = new Set();
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <IconWriting class="size-5 text-primary" />
                    {{ $t('posts.ai.review.title') }}
                </DialogTitle>
                <DialogDescription>{{ $t('posts.ai.review.description') }}</DialogDescription>
            </DialogHeader>

            <div v-if="status === 'loading'" class="flex items-center gap-2 py-8 text-sm text-muted-foreground">
                <IconLoader2 class="size-4 animate-spin" />
                {{ $t('posts.ai.review.loading') }}
            </div>

            <p v-else-if="status === 'failed'" class="py-4 text-sm text-destructive">{{ errorMessage }}</p>

            <p v-else-if="noIssues" class="py-4 text-sm text-muted-foreground">{{ $t('posts.ai.review.no_issues') }}</p>

            <ul v-else-if="suggestions.length > 0" class="max-h-[400px] space-y-3 overflow-y-auto">
                <li
                    v-for="(s, idx) in suggestions"
                    :key="idx"
                    class="rounded-md border bg-card p-3"
                    :class="appliedSet.has(idx) ? 'opacity-60' : ''"
                >
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">{{ $t('posts.ai.review.original') }}</p>
                    <p class="mb-2 text-sm line-through opacity-75">{{ s.original }}</p>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">{{ $t('posts.ai.review.suggestion') }}</p>
                    <p class="mb-2 text-sm font-medium">{{ s.suggestion }}</p>
                    <p class="mb-3 text-xs text-muted-foreground">{{ s.reason }}</p>
                    <Button
                        size="sm"
                        :disabled="appliedSet.has(idx)"
                        @click="applySuggestion(idx, s)"
                    >
                        <IconCheck class="size-4" />
                        {{ appliedSet.has(idx) ? $t('posts.ai.review.applied') : $t('posts.ai.review.apply') }}
                    </Button>
                </li>
            </ul>

            <DialogFooter>
                <Button variant="outline" @click="open = false">{{ $t('posts.ai.review.close') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
