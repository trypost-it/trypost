<script setup lang="ts">
import type { PrimitiveProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import type { ButtonVariants } from "."
import { Primitive } from "reka-ui"
import { cn } from "@/lib/utils"
import { buttonVariants } from "."
import { IconLoader } from "@tabler/icons-vue"

interface Props extends PrimitiveProps {
  variant?: ButtonVariants["variant"]
  size?: ButtonVariants["size"]
  class?: HTMLAttributes["class"]
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  as: "button",
})
</script>

<template>
  <Primitive data-slot="button" :as="as" :as-child="asChild"
    :class="cn(buttonVariants({ variant, size }), props.class, { 'opacity-25 cursor-not-allowed': loading })"
    :disabled="loading">
    <IconLoader v-if="loading" class="size-5 mx-4 animate-spin" />
    <slot v-else />
  </Primitive>
</template>
