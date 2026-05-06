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
    <div class="flex h-full w-64 shrink-0 flex-col border-r-2 border-foreground/10">
        <div class="border-b-2 border-foreground/10 px-4 py-3">
            <h2 class="text-[11px] font-black uppercase tracking-widest text-foreground/60">
                {{ $t('analytics.channels') }}
            </h2>
        </div>
        <div class="flex-1 space-y-1 overflow-y-auto p-2">
            <div v-if="accounts.length === 0" class="px-2 py-8 text-center text-sm font-medium text-foreground/60">
                {{ $t('analytics.no_accounts') }}
            </div>
            <button
                v-for="account in accounts"
                :key="account.id"
                type="button"
                class="flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-left text-sm transition-colors"
                :class="selectedId === account.id ? 'bg-violet-100 text-foreground' : 'text-foreground/80 hover:bg-foreground/5 hover:text-foreground'"
                @click="emit('select', account.id)"
            >
                <div class="relative shrink-0">
                    <Avatar class="size-9 rounded-full border-2 border-foreground shadow-2xs">
                        <AvatarImage v-if="account.avatar_url" :src="account.avatar_url" :alt="account.display_name" />
                        <AvatarFallback class="rounded-full bg-violet-100 font-bold text-foreground">
                            {{ account.display_name?.charAt(0) }}
                        </AvatarFallback>
                    </Avatar>
                    <span class="absolute -bottom-1 -right-1 inline-flex size-5 items-center justify-center overflow-hidden rounded-full border-2 border-foreground bg-card shadow-2xs">
                        <img
                            :src="getPlatformLogo(account.platform)"
                            :alt="account.platform"
                            class="size-full object-cover"
                        />
                    </span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-bold text-foreground">{{ account.display_name }}</p>
                    <p v-if="account.username" class="truncate text-xs font-medium text-foreground/60">
                        @{{ account.username }}
                    </p>
                </div>
            </button>
        </div>
    </div>
</template>
