import { echo } from '@laravel/echo-vue';
import { onUnmounted, ref } from 'vue';

interface TextDeltaEvent {
    delta: string;
}

interface ErrorEvent {
    message?: string;
}

export type AiStreamStatus = 'idle' | 'streaming' | 'completed' | 'failed';

/**
 * Subscribe to a private channel for an in-flight AI generation.
 * Reactive state accumulates `.TextDelta` event deltas and transitions to
 * `completed` on `.StreamEnd` or `failed` on `.Error`.
 */
export const useAiStream = () => {
    const text = ref('');
    const status = ref<AiStreamStatus>('idle');
    const errorMessage = ref<string | null>(null);
    let subscribedName: string | null = null;

    const reset = () => {
        text.value = '';
        status.value = 'idle';
        errorMessage.value = null;
    };

    const unsubscribe = () => {
        if (subscribedName) {
            echo().leave(`private-${subscribedName}`);
        }
        subscribedName = null;
    };

    const subscribe = (channelName: string) => {
        unsubscribe();
        reset();
        status.value = 'streaming';
        subscribedName = channelName;

        echo().private(channelName)
            .listen('.text_delta', (e: TextDeltaEvent) => {
                text.value += e.delta ?? '';
            })
            .listen('.stream_end', () => {
                status.value = 'completed';
            })
            .listen('.error', (e: ErrorEvent) => {
                status.value = 'failed';
                errorMessage.value = e?.message ?? 'AI generation failed';
            });
    };

    onUnmounted(() => unsubscribe());

    return { text, status, errorMessage, subscribe, unsubscribe, reset };
};
