<script lang="ts" setup>
import type { ToasterProps } from "vue-sonner"
import { Toaster as Sonner } from "vue-sonner"

const props = defineProps<ToasterProps>()
</script>

<template>
  <Sonner
    v-bind="props"
    position="bottom-right"
    :style="{
      '--normal-bg': 'var(--card)',
      '--normal-text': 'var(--foreground)',
      '--normal-border': 'var(--foreground)',
      '--success-bg': '#d1fae5',
      '--success-text': 'var(--foreground)',
      '--success-border': 'var(--foreground)',
      '--error-bg': '#fee2e2',
      '--error-text': 'var(--foreground)',
      '--error-border': 'var(--foreground)',
      '--warning-bg': '#fef3c7',
      '--warning-text': 'var(--foreground)',
      '--warning-border': 'var(--foreground)',
      '--info-bg': '#ede9fe',
      '--info-text': 'var(--foreground)',
      '--info-border': 'var(--foreground)',
    }"
  />
</template>

<style>
/* Indies sticker toast — ink 2px border + solid offset ink shadow, no blur. */
[data-sonner-toast] {
  border-width: 2px !important;
  border-radius: var(--radius-lg) !important;
  box-shadow: 4px 4px 0 0 #0a0a0a !important;
  font-weight: 500;
}

[data-sonner-toast] [data-title] {
  font-weight: 600;
}

[data-sonner-toast][data-type="success"] [data-icon] {
  color: #047857 !important;
}

[data-sonner-toast][data-type="error"] [data-icon] {
  color: #b91c1c !important;
}

[data-sonner-toast][data-type="warning"] [data-icon] {
  color: #b45309 !important;
}

[data-sonner-toast][data-type="info"] [data-icon] {
  color: #5b21b6 !important;
}

/* Action / cancel buttons inside a toast pick up the indies button look. */
[data-sonner-toast] [data-button] {
  border: 2px solid #0a0a0a !important;
  box-shadow: 1px 1px 0 0 #0a0a0a !important;
  font-weight: 600;
}
</style>
