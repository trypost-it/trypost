import { ref } from 'vue';

const open = ref(false);
const reason = ref<string | null>(null);

export const useUpgradeDialog = () => ({
    open,
    reason,
    openUpgrade: (cause?: string) => {
        reason.value = cause ?? null;
        open.value = true;
    },
    closeUpgrade: () => {
        open.value = false;
        reason.value = null;
    },
});
