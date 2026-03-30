<script setup lang="ts">
import { Avatar } from '@/components/ui/avatar';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
    avatarClass?: string;
    fallbackClass?: string;
};

withDefaults(defineProps<Props>(), {
    showEmail: false,
});
</script>

<template>
    <Avatar
        :src="user.photo_url"
        :name="user.name"
        :class="['h-8 w-8 rounded-lg', avatarClass]"
        :fallback-class="
            fallbackClass ?? 'bg-sidebar-accent text-sidebar-accent-foreground'
        "
    />

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{
            user.email
        }}</span>
    </div>
</template>
