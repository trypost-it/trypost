<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { IconCopy, IconX } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { copyToClipboard } from '@/lib/utils';

const props = defineProps({
    title: {
        type: String,
        default: 'Are you sure?',
    },

    description: {
        type: String,
        default: 'Are you sure you want to perform this action?',
    },

    action: {
        type: String,
        default: 'Delete',
    },

    cancel: {
        type: String,
        default: 'Cancel',
    },

    method: {
        type: String,
        default: 'delete',
    },
});

const emit = defineEmits(['deleted', 'closed']);

const isOpen = ref(false);
const processing = ref(false);
const url = ref<string | null>(null);
const confirmInput = ref('');
const confirmText = ref('');

const requiresConfirmation = computed(() => confirmText.value.length > 0);
const isConfirmed = computed(
    () =>
        !requiresConfirmation.value || confirmInput.value === confirmText.value,
);

const remove = () => {
    if (!url.value || !isConfirmed.value) return;

    processing.value = true;

    const options = {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            close();
            emit('deleted');
        },
        onFinish: () => {
            processing.value = false;
        },
    };

    const method = props.method as 'delete' | 'get' | 'post' | 'put' | 'patch';

    if (method === 'delete' || method === 'get') {
        router[method](url.value, options);
    } else {
        router[method](url.value, {}, options);
    }
};

const open = (data: { url: string; confirmText?: string }) => {
    url.value = data.url;
    confirmText.value = data.confirmText ?? '';
    processing.value = false;
    confirmInput.value = '';
    isOpen.value = true;
};

const close = () => {
    isOpen.value = false;
    processing.value = false;
    confirmInput.value = '';
    emit('closed');
};

const onOpenChange = (value: boolean) => {
    isOpen.value = value;
    if (!value) {
        close();
    }
};

defineExpose({
    open,
    close,
});
</script>

<template>
    <Dialog :open="isOpen" @update:open="onOpenChange">
        <DialogContent :show-close-button="false" class="sm:max-w-md">
            <Button
                variant="ghost"
                size="icon"
                class="absolute top-4 right-4 size-7"
                @click="close"
            >
                <IconX class="size-4" />
                <span class="sr-only">Close</span>
            </Button>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription class="space-y-1">
                    <span class="block">{{ description }}</span>
                    <span class="block font-semibold text-destructive">
                        {{ trans('common.confirm_modal.cannot_be_undone') }}
                    </span>
                </DialogDescription>
            </DialogHeader>
            <div v-if="requiresConfirmation" class="py-2">
                <p
                    class="mb-2 flex flex-wrap items-center gap-1 text-sm text-muted-foreground"
                >
                    <span>{{ trans('common.confirm_modal.type') }}</span>
                    <code
                        class="inline-flex items-center gap-1 rounded-sm border bg-zinc-200 px-1.5 text-sm break-all text-foreground dark:bg-zinc-700"
                    >
                        {{ confirmText }}
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <button
                                        type="button"
                                        tabindex="-1"
                                        class="inline-flex shrink-0 items-center rounded text-muted-foreground hover:text-foreground"
                                        @click="
                                            copyToClipboard(
                                                confirmText,
                                                trans('common.confirm_modal.copy_to_clipboard'),
                                            )
                                        "
                                    >
                                        <IconCopy class="size-3" />
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{{ trans('common.confirm_modal.copy_to_clipboard') }}</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </code>
                    <span>{{ trans('common.confirm_modal.to_confirm') }}</span>
                </p>
                <Input
                    v-model="confirmInput"
                    autocomplete="off"
                />
            </div>
            <DialogFooter>
                <Button
                    variant="destructive"
                    :disabled="processing || !isConfirmed"
                    @click="remove"
                >
                    {{ action }}
                </Button>
                <Button
                    variant="secondary"
                    @click="close"
                >
                    {{ cancel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
