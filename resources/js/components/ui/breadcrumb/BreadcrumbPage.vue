<script lang="ts" setup>
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <span
    data-slot="breadcrumb-page"
    role="link"
    aria-disabled="true"
    aria-current="page"
    style="font-family: var(--font-display)"
    :class="cn('text-base leading-none text-foreground', props.class)"
  >
    <slot />
  </span>
</template>
