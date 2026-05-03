<script setup lang="ts">
import { IconLoader2, IconRefresh, IconSparkles, IconWriting } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { useAiStream } from '@/composables/useAiStream';
import { generate as generatePostAi } from '@/routes/app/posts/ai';

const props = defineProps<{
    postId: string;
    currentContent: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    (e: 'apply', content: string): void;
}>();

const prompt = ref('');
const dispatching = ref(false);
const { text, status, errorMessage, subscribe, unsubscribe, reset } = useAiStream();

const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const startGeneration = async () => {
    if (! prompt.value.trim()) return;
    dispatching.value = true;
    try {
        const response = await fetch(generatePostAi.url(props.postId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                prompt: prompt.value,
                current_content: props.currentContent || null,
            }),
        });
        if (! response.ok) {
            status.value = 'failed';
            errorMessage.value = 'Could not start generation';
            return;
        }
        const data = await response.json();
        subscribe(data.channel);
    } finally {
        dispatching.value = false;
    }
};

const apply = () => {
    emit('apply', text.value);
    open.value = false;
};

const retry = () => {
    unsubscribe();
    reset();
    startGeneration();
};

const canApply = computed(() => status.value === 'completed' && text.value.trim().length > 0);
const canRetry = computed(() => status.value === 'completed' || status.value === 'failed');

watch(open, (isOpen) => {
    if (! isOpen) {
        unsubscribe();
        reset();
        prompt.value = '';
    } else {
        prompt.value = props.currentContent || '';
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <IconSparkles class="size-5 text-primary" />
                    {{ $t('posts.ai.generate.title') }}
                </DialogTitle>
                <DialogDescription>{{ $t('posts.ai.generate.description') }}</DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div class="space-y-2">
                    <label class="text-sm font-medium">{{ $t('posts.ai.generate.prompt_label') }}</label>
                    <Textarea
                        v-model="prompt"
                        :placeholder="$t('posts.ai.generate.prompt_placeholder')"
                        :disabled="status === 'streaming'"
                        rows="3"
                    />
                </div>

                <div v-if="status !== 'idle'" class="space-y-2">
                    <label class="flex items-center gap-1.5 text-sm font-medium">
                        <IconWriting class="size-4 text-muted-foreground" />
                        {{ $t('posts.ai.generate.preview_label') }}
                        <IconLoader2 v-if="status === 'streaming'" class="size-3.5 animate-spin text-muted-foreground" />
                    </label>
                    <div class="min-h-[120px] whitespace-pre-wrap rounded-md border bg-muted/30 px-3 py-2 text-sm">{{ text || '...' }}</div>
                    <p v-if="status === 'failed'" class="text-xs text-destructive">{{ errorMessage }}</p>
                </div>
            </div>

            <DialogFooter class="gap-2 sm:gap-2">
                <Button v-if="canRetry" variant="outline" @click="retry">
                    <IconRefresh class="size-4" />
                    {{ $t('posts.ai.generate.retry') }}
                </Button>
                <Button
                    v-if="status === 'idle'"
                    :disabled="! prompt.trim() || dispatching"
                    @click="startGeneration"
                >
                    <IconSparkles class="size-4" />
                    {{ $t('posts.ai.generate.start') }}
                </Button>
                <Button
                    v-if="canApply"
                    @click="apply"
                >
                    {{ $t('posts.ai.generate.apply') }}
                </Button>
                <Button variant="outline" @click="open = false">
                    {{ $t('posts.ai.generate.cancel') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
