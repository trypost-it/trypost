<script setup lang="ts">
import type { LabelProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { Label } from "reka-ui"
import { IconInfoCircle } from "@tabler/icons-vue"
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip"

import { cn } from "@/lib/utils"

const props = defineProps<LabelProps & { class?: HTMLAttributes["class"], required?: boolean, tooltip?: string }>()

const delegatedProps = reactiveOmit(props, "class", "required", "tooltip")
</script>

<template>
  <Label data-slot="label" v-bind="delegatedProps" :class="cn(
    'flex items-center gap-0.5 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50',
    props.class,
  )
    ">
    <slot />
    <span v-if="required" class="text-red-500">*</span>
    <TooltipProvider v-if="tooltip">
      <Tooltip>
        <TooltipTrigger as-child>
          <IconInfoCircle class="size-4 text-muted-foreground cursor-help" />
        </TooltipTrigger>
        <TooltipContent>
          <p>{{ tooltip }}</p>
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>
  </Label>
</template>