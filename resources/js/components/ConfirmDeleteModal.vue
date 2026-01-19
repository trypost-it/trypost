<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';

const props = defineProps({
  title: {
    type: String,
    default: 'Are you sure?',
  },

  description: {
    type: String,
    default:
      'Are you sure you want to perform this action? This action cannot be undone.',
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

  preserveState: {
    type: Boolean,
    default: false,
  },

  preserveScroll: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['deleted', 'closed']);

const isOpen = ref<boolean>(false);
const loading = ref<boolean>(false);
const url = ref<string | null>(null);

function remove() {
  if (!url.value) return;

  loading.value = true;

  router[props.method as 'delete' | 'get' | 'post' | 'put' | 'patch'](
    url.value,
    {},
    {
      preserveState: props.preserveState,
      preserveScroll: props.preserveScroll,
      onSuccess: () => {
        close();
        emit('deleted');
      },
      onError: (errors) => {
        console.error(errors);
      },
      onFinish: () => {
        loading.value = false;
      },
    },
  );
}

function open(data: { url: string }) {
  url.value = data.url;
  loading.value = false;
  isOpen.value = true;
}

function close() {
  isOpen.value = false;
  loading.value = false;
  emit('closed');
}

function onOpenChange(value: boolean) {
  isOpen.value = value;
  if (!value) {
    close();
  }
}

defineExpose({
  open,
  close,
});
</script>

<template>
  <AlertDialog :open="isOpen" @update:open="onOpenChange">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>{{ title }}</AlertDialogTitle>
        <AlertDialogDescription>
          {{ description }}
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel :disabled="loading">
          {{ cancel }}
        </AlertDialogCancel>
        <AlertDialogAction :disabled="loading" variant="default" @click="remove">
          {{ action }}
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>