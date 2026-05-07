import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import type { Page } from '@inertiajs/core';
import { configureEcho } from '@laravel/echo-vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { i18nVue } from 'laravel-vue-i18n';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';

import { initializeDataLayer } from './datalayer';
import dayjs from './dayjs';
import posthog from './posthog';
import type { Auth } from './types';

interface Usage {
    workspaceCount: number;
    socialAccountCount: number;
    memberCount: number;
    pendingInviteCount: number;
    postCount: number;
    creditsUsed: number;
}

configureEcho({
    broadcaster: 'reverb',
});

const appName = import.meta.env.VITE_APP_NAME || 'TryPost.it';

// Re-applies the PostHog account/workspace group context with fresh metrics.
// Called on every Inertia navigation so `usage` counts (workspaces, social
// accounts, posts, members) stay reactive — the backend already ships these
// counts on every Inertia request, so this leverages props that are already
// loaded without firing extra queries.
const syncPostHogContext = (page: Page): void => {
    const auth = page.props.auth as Auth | undefined;
    const usage = page.props.usage as Usage | null;

    if (!auth?.user) return;

    if (auth.account) {
        posthog.group('account', auth.account.id, {
            name: auth.account.name,
            workspaces_count: usage?.workspaceCount,
            social_accounts_count: usage?.socialAccountCount,
            members_count: usage?.memberCount,
            posts_count: usage?.postCount,
            credits_used: usage?.creditsUsed,
        });
    }

    if (auth.currentWorkspace) {
        posthog.group('workspace', auth.currentWorkspace.id, {
            name: auth.currentWorkspace.name,
            account_id: auth.account?.id,
        });
    }
};

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

        if (auth?.user) {
            posthog.identify(auth.user.id, {
                $email: auth.user.email,
                $name: auth.user.name,
            });
        }

        // Initial group context + initial pageview. The backend mirrors this
        // hierarchy in app/Jobs/SyncUserToPostHog.php so events emitted from
        // the server land on the same person + group identifiers.
        syncPostHogContext(props.initialPage);
        posthog.capture('$pageview', { $current_url: window.location.href });

        router.on('navigate', (event) => {
            // Re-sync group context on every navigation: refreshes the count
            // metrics on the `account` group AND covers workspace switches
            // (which update auth.currentWorkspace via Inertia's prop refresh
            // without triggering setup() again).
            syncPostHogContext(event.detail.page);
            posthog.capture('$pageview', { $current_url: window.location.href });
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
