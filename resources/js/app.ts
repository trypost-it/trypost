import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { i18nVue } from 'laravel-vue-i18n';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';

import { initializeDataLayer } from './datalayer';
import dayjs from './dayjs';
import { capturePageview, initializePostHog, syncPostHogContext } from './posthog';
import type { Auth } from './types';
import { useUpgradeDialog } from '@/composables/useUpgradeDialog';

configureEcho({
    broadcaster: 'reverb',
});

const appName = import.meta.env.VITE_APP_NAME || 'TryPost.it';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // Get locale from shared Inertia props
        const locale = (props.initialPage.props as { locale?: string })?.locale || 'en';

        // Set dayjs locale based on user's language
        dayjs.locale(locale.toLowerCase());

        const auth = props.initialPage.props.auth as Auth | undefined;
        const flash = props.initialPage.props.flash as
            | { conversion_event?: string; [key: string]: unknown }
            | undefined;

        initializeDataLayer(
            auth,
            flash,
            props.initialPage.props.applicationUrl as string,
            props.initialPage.props.env as string,
        );

        // Initial PostHog identify + dual-group context + first pageview.
        // The same hooks fire on every Inertia navigation below so the
        // account group counts stay reactive and workspace switches
        // re-attach the right workspace group.
        initializePostHog();
        syncPostHogContext(props.initialPage);
        capturePageview();

        router.on('navigate', (event) => {
            syncPostHogContext(event.detail.page);
            capturePageview();
        });

        // Global 402 interceptor: wire upgrade_required responses to UpgradeDialog.
        router.on('httpException', (event: any) => {
            const response = event.detail?.response;
            if (response?.status !== 402) return;

            const data = response.data;
            if (!data?.upgrade_required) return;

            const { openUpgrade } = useUpgradeDialog();
            const info =
                data.limit !== undefined && data.current !== undefined
                    ? { limit: data.limit as number, current: data.current as number }
                    : undefined;

            openUpgrade(data.reason as string | undefined, info);
            event.preventDefault();
        });

        createApp({ render: () => h(App, props) })
            .use(i18nVue, {
                lang: locale,
                resolve: async (lang: string) => {
                    const langs = import.meta.glob('../../lang/*.json');
                    return await langs[`../../lang/php_${lang}.json`]();
                },
            })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
