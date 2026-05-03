<script setup lang="ts">
import { Head, InfiniteScroll, router } from '@inertiajs/vue3';
import { IconBookmarks, IconLoader2, IconSearch } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

import { apply as applyRoute, index as templatesIndex } from '@/actions/App/Http/Controllers/App/PostTemplateController';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { getPlatformLogo } from '@/composables/usePlatformLogo';
import debounce from '@/debounce';
import AppLayout from '@/layouts/AppLayout.vue';
import { calendar } from '@/routes/app';

interface Slide {
    title: string;
    body: string;
    image_keywords: string[];
}

interface PostTemplate {
    id: string;
    name: string;
    description: string | null;
    category: string;
    platform: string;
    content: string;
    slides: Slide[] | null;
    image_count: number;
    image_keywords: string[] | null;
}

interface ScrollTemplates {
    data: PostTemplate[];
}

interface Props {
    templates: ScrollTemplates;
    filters: {
        search: string;
        platform: string;
    };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search);
const applyingId = ref<string | null>(null);

const PLATFORM_LABELS: Record<string, string> = {
    instagram_carousel: 'Instagram Carousel',
    instagram_feed: 'Instagram Feed',
    linkedin_post: 'LinkedIn',
    linkedin_page_post: 'LinkedIn Page',
    x_post: 'X',
};

const PLATFORM_LOGO_KEY: Record<string, string> = {
    instagram_carousel: 'instagram',
    instagram_feed: 'instagram',
    linkedin_post: 'linkedin',
    linkedin_page_post: 'linkedin-page',
    x_post: 'x',
};

const platformLabel = (platform: string): string => PLATFORM_LABELS[platform] ?? platform;
const platformLogo = (platform: string): string => getPlatformLogo(PLATFORM_LOGO_KEY[platform] ?? platform);

const search = debounce(() => {
    router.get(
        templatesIndex.url(),
        { search: searchQuery.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}, 300);

watch(searchQuery, () => search());

const hasActiveSearch = computed(() => Boolean(searchQuery.value?.trim()));

const applyTemplate = async (template: PostTemplate) => {
    if (applyingId.value) return;
    applyingId.value = template.id;

    try {
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
        const response = await fetch(applyRoute.url(template.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({}),
        });

        if (!response.ok) {
            const err = await response.json().catch(() => ({}));
            throw new Error(err?.message ?? `HTTP ${response.status}`);
        }

        const data = await response.json();
        router.visit(data.redirect_url);
    } finally {
        applyingId.value = null;
    }
};

const breadcrumbs = computed(() => [
    { title: trans('sidebar.posts.calendar'), href: calendar.url() },
    { title: trans('posts.templates.browser_title'), href: '' },
]);

const pageTitle = computed(() => trans('posts.templates.browser_title'));
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <PageHeader :title="pageTitle" :description="$t('posts.templates.browser_description')" />

            <!-- Search bar -->
            <div class="relative max-w-md">
                <IconSearch class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    :placeholder="$t('posts.templates.search_placeholder')"
                    class="pl-9"
                />
            </div>

            <EmptyState
                v-if="templates.data.length === 0"
                :icon="IconBookmarks"
                :title="hasActiveSearch ? $t('posts.templates.no_search_results') : $t('posts.templates.no_templates')"
                :description="hasActiveSearch ? $t('posts.templates.try_different_search') : ''"
            />

            <InfiniteScroll v-else data="templates" items-element="#templates-grid" preserve-url>
                <!-- CSS columns masonry: cards keep their natural height + don't split mid-card -->
                <div id="templates-grid" class="columns-1 gap-4 sm:columns-2 lg:columns-3 xl:columns-4">
                    <article
                        v-for="template in templates.data"
                        :key="template.id"
                        class="mb-4 break-inside-avoid rounded-xl border bg-card p-5 transition-colors hover:border-primary/40"
                    >
                        <header class="flex items-center gap-2">
                            <img
                                :src="platformLogo(template.platform)"
                                :alt="template.platform"
                                class="size-6 shrink-0 rounded-full ring-1 ring-border"
                            />
                            <span class="truncate text-xs font-medium text-muted-foreground">{{ platformLabel(template.platform) }}</span>
                            <Badge variant="secondary" class="ml-auto shrink-0 text-xs font-normal">
                                {{ $t(`posts.templates.category.${template.category}`) }}
                            </Badge>
                        </header>

                        <h3 class="mt-3 text-base font-semibold leading-snug tracking-tight">{{ template.name }}</h3>

                        <p
                            v-if="template.description"
                            class="mt-2 text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ template.description }}
                        </p>

                        <p
                            v-if="template.slides && template.slides.length > 0"
                            class="mt-3 text-xs text-muted-foreground"
                        >
                            {{ $t('posts.templates.slides_count', { count: template.slides.length }) }}
                        </p>

                        <Button
                            size="sm"
                            variant="outline"
                            class="mt-4 w-full"
                            :disabled="applyingId === template.id"
                            @click="applyTemplate(template)"
                        >
                            <IconLoader2 v-if="applyingId === template.id" class="mr-1 size-3 animate-spin" />
                            {{ applyingId === template.id ? $t('posts.templates.applying') : $t('posts.templates.use_this') }}
                        </Button>
                    </article>
                </div>
            </InfiniteScroll>
        </div>
    </AppLayout>
</template>
