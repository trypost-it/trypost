<script setup lang="ts">
import { IconCalendar, IconCircleCheck, IconLoader2, IconTrash } from '@tabler/icons-vue';
import { computed } from 'vue';

import PickTimePopover from '@/components/posts/PickTimePopover.vue';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

interface Props {
    post: { status: string };
    isSaving: boolean;
    showSaved: boolean;
    isSubmitting: boolean;
    isPostActionDisabled: boolean;
    postActionTooltip: string;
    pickTimeLabel: string;
}

const props = defineProps<Props>();

const hasPickedTime = defineModel<boolean>('hasPickedTime', { required: true });
const scheduledDateTime = defineModel<string>('scheduledDateTime', { required: true });

const emit = defineEmits<{
    (e: 'delete'): void;
    (e: 'unschedule'): void;
    (e: 'submit', status: string): void;
}>();

const isReadOnly = computed(() => ['publishing', 'published', 'partially_published'].includes(props.post.status));
const isScheduled = computed(() => props.post.status === 'scheduled');
const isPublished = computed(() => ['published', 'partially_published'].includes(props.post.status));
</script>

<template>
    <header
        :class="[
            'flex shrink-0 items-center gap-3 border-b-2 border-foreground px-4 py-3 md:px-6',
            isScheduled ? 'bg-violet-100' : 'justify-between bg-card',
        ]"
    >
        <template v-if="isScheduled">
            <div class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg border-2 border-foreground bg-violet-200">
                <IconCalendar class="size-4 text-foreground" stroke-width="2" />
            </div>
            <div class="flex-1 leading-tight">
                <p class="text-sm font-semibold text-foreground">
                    {{ $t('posts.edit.scheduled_overlay_title') }}
                </p>
                <p class="text-xs text-foreground/70">
                    {{ $t('posts.edit.scheduled_overlay_subtitle', { date: pickTimeLabel }) }}
                </p>
            </div>
            <Button
                type="button"
                variant="outline"
                class="bg-background hover:bg-violet-50"
                :disabled="isSubmitting"
                @click="emit('unschedule')"
            >
                {{ $t('posts.edit.unschedule_cta') }}
            </Button>
        </template>

        <template v-else>
            <div class="flex items-center gap-3 pl-12 md:pl-0">
                <span v-if="isSaving" class="flex items-center gap-1.5 text-xs font-semibold text-foreground/70">
                    <IconLoader2 class="size-3.5 animate-spin" />
                    {{ $t('posts.edit.saving') }}
                </span>
                <span v-else-if="showSaved" class="flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                    <IconCircleCheck class="size-3.5" stroke-width="2.5" />
                    {{ $t('posts.edit.saved') }}
                </span>
                <span v-else-if="isPublished" class="flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                    <IconCircleCheck class="size-3.5" stroke-width="2.5" />
                    {{ $t('posts.edit.status.published') }}
                </span>
                <span v-else class="flex items-center gap-1.5 text-xs font-semibold text-foreground/60">
                    <span class="size-2 rounded-full bg-foreground/40" />
                    {{ $t('posts.edit.draft') }}
                </span>
            </div>

            <div v-if="!isReadOnly" class="flex items-center gap-2">
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="bg-rose-100 hover:bg-rose-200"
                                :disabled="isSaving || isSubmitting"
                                @click="emit('delete')"
                            >
                                <IconTrash class="size-4 text-rose-700" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{ $t('posts.edit.delete') }}</TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <PickTimePopover
                    v-model="scheduledDateTime"
                    :disabled="isPostActionDisabled"
                    @confirm="hasPickedTime = true"
                >
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="isPostActionDisabled"
                        :title="postActionTooltip"
                    >
                        <IconCalendar class="size-4" />
                        {{ pickTimeLabel }}
                    </Button>
                </PickTimePopover>

                <Button
                    type="button"
                    :disabled="isPostActionDisabled"
                    :title="postActionTooltip"
                    @click="emit('submit', hasPickedTime ? 'scheduled' : 'publishing')"
                >
                    {{ hasPickedTime ? $t('posts.edit.schedule') : $t('posts.edit.post_now') }}
                </Button>
            </div>
        </template>
    </header>
</template>
