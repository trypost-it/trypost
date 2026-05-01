<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getPlatformLogo } from '@/composables/usePlatformLogo';

export interface AnalyticsAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string | null;
    avatar_url: string | null;
}

defineProps<{
    accounts: AnalyticsAccount[];
    selectedId: string | null;
}>();

const emit = defineEmits<{
    select: [accountId: string];
}>();
</script>

<template>
    <div class="flex h-full w-64 shrink-0 flex-col border-r">
        <div class="border-b px-4 py-3">
            <h2 class="text-sm font-semibold">{{ $t('analytics.channels') }}</h2>
        </div>
        <div class="flex-1 overflow-y-auto p-2">
            <div v-if="accounts.length === 0" class="px-2 py-8 text-center text-sm text-muted-foreground">
                {{ $t('analytics.no_accounts') }}
            </div>
            <button
                v-for="account in accounts"
                :key="account.id"
                type="button"
                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm transition-colors"
                :class="selectedId === account.id ? 'bg-accent text-accent-foreground' : 'hover:bg-muted'"
                @click="emit('select', account.id)"
            >
                <div class="relative">
                    <Avatar class="h-8 w-8">
                        <AvatarImage v-if="account.avatar_url" :src="account.avatar_url" :alt="account.display_name" />
                        <AvatarFallback>{{ account.display_name?.charAt(0) }}</AvatarFallback>
                    </Avatar>
                    <img
                        :src="getPlatformLogo(account.platform)"
                        :alt="account.platform"
                        class="absolute -bottom-0.5 -right-0.5 h-4 w-4 rounded-full border border-background"
                    />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ account.display_name }}</p>
                    <p v-if="account.username" class="truncate text-xs text-muted-foreground">
                        @{{ account.username }}
                    </p>
                </div>
            </button>
        </div>
    </div>
</template>
