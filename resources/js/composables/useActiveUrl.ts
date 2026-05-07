import type { InertiaLinkProps } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { computed, readonly } from 'vue';

import { toUrl } from '@/lib/utils';

const page = usePage();
const currentUrlReactive = computed(
    () => new URL(page.url, window?.location.origin).pathname,
);

const toPathname = (url: string): string => {
    try {
        return new URL(url, window.location.origin).pathname;
    } catch {
        return url;
    }
};

export function useActiveUrl() {
    function urlIsActive(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        options?: { prefix?: boolean; exact?: boolean; exclude?: string[] },
    ) {
        const current = currentUrlReactive.value;
        const pathname = toPathname(toUrl(urlToCheck));

        if (options?.exact) {
            return current === pathname;
        }

        if (options?.prefix) {
            return current.startsWith(pathname);
        }

        const isMatch =
            current === pathname ||
            (pathname !== '/' && current.startsWith(pathname + '/'));

        if (isMatch && options?.exclude) {
            return !options.exclude.some(
                (ex) =>
                    current === toPathname(ex) ||
                    current.startsWith(toPathname(ex) + '/'),
            );
        }

        return isMatch;
    }

    return {
        currentUrl: readonly(currentUrlReactive),
        urlIsActive,
    };
}
