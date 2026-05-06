<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <tr
    data-slot="table-row"
    :class="cn('border-b border-dashed border-foreground/20 transition-colors hover:bg-foreground/5 data-[state=selected]:bg-accent last:border-b-0', props.class)"
  >
    <slot />
  </tr>
</template>
