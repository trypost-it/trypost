<script setup lang="ts">
import type { ComboboxInputEmits, ComboboxInputProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { IconSearch } from '@tabler/icons-vue'
import {
  ComboboxInput,

  useForwardPropsEmits,
} from "reka-ui"

import { cn } from "@/lib/utils"

defineOptions({
  inheritAttrs: false,
})

const props = defineProps<
  ComboboxInputProps & {
    class?: HTMLAttributes["class"]
  }
>()

const emits = defineEmits<ComboboxInputEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <div
    data-slot="command-input-wrapper"
    class="flex h-10 items-center gap-2 border-b-2 border-foreground/10 px-3"
  >
    <IconSearch class="size-4 shrink-0 text-foreground/60" />
    <ComboboxInput
      data-slot="command-input"
      :class="
        cn(
          'placeholder:text-foreground/50 flex h-10 w-full rounded-md bg-transparent py-3 text-sm font-medium text-foreground outline-hidden disabled:cursor-not-allowed disabled:opacity-50',
          props.class,
        )
      "
      v-bind="{ ...forwarded, ...$attrs }"
    >
      <slot />
    </ComboboxInput>
  </div>
</template>
