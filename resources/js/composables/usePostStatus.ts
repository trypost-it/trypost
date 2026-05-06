import {
    IconAlertCircle,
    IconCircleCheck,
    IconClock,
    IconFileText,
    IconLoader2,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'success' | 'warning' | 'outline';

interface StatusConfig {
    variant: BadgeVariant;
    icon: typeof IconFileText;
    label: string;
}

const CONFIGS: Record<string, Pick<StatusConfig, 'variant' | 'icon'>> = {
    draft: { variant: 'outline', icon: IconFileText },
    scheduled: { variant: 'default', icon: IconClock },
    publishing: { variant: 'warning', icon: IconLoader2 },
    published: { variant: 'success', icon: IconCircleCheck },
    partially_published: { variant: 'warning', icon: IconAlertCircle },
    failed: { variant: 'destructive', icon: IconAlertCircle },
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
