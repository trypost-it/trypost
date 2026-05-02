import {
    IconAlertCircle,
    IconCircleCheck,
    IconClock,
    IconFileText,
    IconLoader2,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';

interface StatusConfig {
    color: string;
    icon: typeof IconFileText;
    label: string;
}

const CONFIGS: Record<string, Pick<StatusConfig, 'color' | 'icon'>> = {
    draft: { color: 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200', icon: IconFileText },
    scheduled: { color: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300', icon: IconClock },
    publishing: { color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-300', icon: IconLoader2 },
    published: { color: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300', icon: IconCircleCheck },
    partially_published: { color: 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300', icon: IconAlertCircle },
    failed: { color: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300', icon: IconAlertCircle },
};

export const getPostStatusConfig = (status: string): StatusConfig => {
    const config = CONFIGS[status] ?? CONFIGS.draft;
    return { ...config, label: trans(`posts.status.${status}`) };
};

export const getPlatformStatusConfig = (status: string): StatusConfig => {
    const map: Record<string, string> = {
        pending: 'draft',
        publishing: 'publishing',
        published: 'published',
        failed: 'failed',
    };
    const key = map[status] ?? 'draft';
    const config = CONFIGS[key];
    return { ...config, label: trans(`posts.edit.status.${status}`) };
};
