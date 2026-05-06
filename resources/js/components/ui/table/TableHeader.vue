<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <thead
    data-slot="table-header"
    :class="cn('bg-muted [&_tr]:!border-b-2 [&_tr]:!border-solid [&_tr]:!border-foreground', props.class)"
  >
    <slot />
  </thead>
</template>
