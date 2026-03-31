import { AppPageProps } from '@/types/index';

declare module '@unovis/vue' {
    export const VisXYContainer: any;
    export const VisLine: any;
    export const VisAxis: any;
    export const VisArea: any;
    export const VisStackedBar: any;
    export const VisBulletLegend: any;
    export const VisTooltip: any;
    export const VisCrosshair: any;
}

declare module '@unovis/ts' {
    export class Line<T = any> {}
    export class Area<T = any> {}
    export class StackedBar<T = any> {}
    export class Axis<T = any> {}
    export class Crosshair<T = any> {}
    export class Tooltip<T = any> {}
    export class BulletLegend<T = any> {}
}

declare global {
    interface Window {
        dataLayer: Record<string, unknown>[];
    }
}

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    interface PageProps extends InertiaPageProps, AppPageProps {}
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
