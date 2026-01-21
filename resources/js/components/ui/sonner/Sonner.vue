<script lang="ts" setup>
import type { ToasterProps } from "vue-sonner"
import { IconCircleCheck, IconInfoCircle, IconLoader2, IconAlertOctagon, IconAlertTriangle, IconX } from "@tabler/icons-vue"
import { Toaster as Sonner } from "vue-sonner"
import { cn } from "@/lib/utils"

const props = defineProps<ToasterProps>()
</script>

<template>
  <Sonner
    :class="cn('toaster group', props.class)"
    :style="{
      '--normal-bg': 'var(--popover)',
      '--normal-text': 'var(--popover-foreground)',
      '--normal-border': 'var(--border)',
      '--border-radius': 'var(--radius)',
    }"
    v-bind="props"
  >
    <template #success-icon>
      <IconCircleCheck class="size-4" />
    </template>
    <template #info-icon>
      <IconInfoCircle class="size-4" />
    </template>
    <template #warning-icon>
      <IconAlertTriangle class="size-4" />
    </template>
    <template #error-icon>
      <IconAlertOctagon class="size-4" />
    </template>
    <template #loading-icon>
      <div>
        <IconLoader2 class="size-4 animate-spin" />
      </div>
    </template>
    <template #close-icon>
      <IconX class="size-4" />
    </template>
  </Sonner>
</template>
