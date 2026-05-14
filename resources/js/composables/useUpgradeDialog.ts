import { ref } from 'vue';

const open = ref(false);
const reason = ref<string | null>(null);
const limitInfo = ref<{ limit: number; current: number } | null>(null);

export const useUpgradeDialog = () => ({
    open,
    reason,
    limitInfo,
    openUpgrade: (cause?: string, info?: { limit: number; current: number }) => {
        reason.value = cause ?? null;
        limitInfo.value = info ?? null;
        open.value = true;
    },
    closeUpgrade: () => {
        open.value = false;
        reason.value = null;
        limitInfo.value = null;
    },
});
