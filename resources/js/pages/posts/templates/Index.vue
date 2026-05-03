<script setup lang="ts">
import { Head, InfiniteScroll, router, useHttp } from '@inertiajs/vue3';
import { IconBookmarks, IconLoader2, IconSearch } from '@tabler/icons-vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';

import { apply as applyRoute, index as templatesIndex } from '@/actions/App/Http/Controllers/App/PostTemplateController';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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
    slug: string;
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
const selectedPlatform = ref<string>(props.filters.platform || '');
const applyingSlug = ref<string | null>(null);

const PLATFORM_BRAND: Record<string, string> = {
    instagram_carousel: 'Instagram',
    instagram_feed: 'Instagram',
    instagram_story: 'Instagram',
    linkedin_post: 'LinkedIn',
    linkedin_page_post: 'LinkedIn Page',
    x_post: 'X',
    threads_post: 'Threads',
    bluesky_post: 'Bluesky',
    mastodon_post: 'Mastodon',
    facebook_post: 'Facebook',
    facebook_story: 'Facebook',
    pinterest_pin: 'Pinterest',
};

const FORMAT_LABEL: Record<string, string> = {
    instagram_carousel: 'Carousel',
    instagram_feed: 'Feed',
    instagram_story: 'Story',
    linkedin_post: 'Post',
    linkedin_page_post: 'Page post',
    x_post: 'Post',
    threads_post: 'Post',
    bluesky_post: 'Post',
    mastodon_post: 'Post',
    facebook_post: 'Post',
    facebook_story: 'Story',
    pinterest_pin: 'Pin',
};

const PLATFORM_LOGO_KEY: Record<string, string> = {
    instagram_carousel: 'instagram',
    instagram_feed: 'instagram',
    instagram_story: 'instagram',
    linkedin_post: 'linkedin',
    linkedin_page_post: 'linkedin-page',
    x_post: 'x',
    threads_post: 'threads',
    bluesky_post: 'bluesky',
    mastodon_post: 'mastodon',
    facebook_post: 'facebook',
    facebook_story: 'facebook',
    pinterest_pin: 'pinterest',
};

const platformBrand = (platform: string): string => PLATFORM_BRAND[platform] ?? platform;
const formatLabel = (platform: string): string => FORMAT_LABEL[platform] ?? '';
const platformLogo = (platform: string): string => getPlatformLogo(PLATFORM_LOGO_KEY[platform] ?? platform);

const platformOptions = computed(() =>
    Object.keys(PLATFORM_BRAND).map((value) => ({
        value,
        brand: PLATFORM_BRAND[value],
        format: FORMAT_LABEL[value],
        logo: platformLogo(value),
    })),
);

const selectedPlatformOption = computed(() =>
    platformOptions.value.find((p) => p.value === selectedPlatform.value),
);

const reload = (params: { search?: string; platform?: string }) => {
    router.get(
        templatesIndex.url(),
        {
            search: params.search || undefined,
            platform: params.platform || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const search = debounce(() => {
    reload({ search: searchQuery.value, platform: selectedPlatform.value });
}, 300);

watch(searchQuery, () => search());

const onPlatformChange = (next: string | number | undefined) => {
    const value = next === 'all' || !next ? '' : String(next);
    selectedPlatform.value = value;
    reload({ search: searchQuery.value, platform: value });
};

const hasActiveSearch = computed(() => Boolean(searchQuery.value?.trim()) || Boolean(selectedPlatform.value));

const applyTemplate = async (template: PostTemplate) => {
    if (applyingSlug.value) return;
    applyingSlug.value = template.slug;

    try {
        const data = (await useHttp().post(applyRoute.url(template.slug))) as {
            post_id: string;
            redirect_url: string;
        };
        router.visit(data.redirect_url);
    } finally {
        applyingSlug.value = null;
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

            <!-- Filters: platform + search -->
            <div class="flex flex-wrap items-center gap-3">
                <Select
                    :model-value="selectedPlatform || 'all'"
                    @update:model-value="onPlatformChange"
                >
                    <SelectTrigger class="w-56">
                        <SelectValue>
                            <span v-if="!selectedPlatformOption" class="text-muted-foreground">
                                {{ $t('posts.templates.all_platforms') }}
                            </span>
                            <span v-else class="flex items-center gap-2">
                                <img
                                    :src="selectedPlatformOption.logo"
                                    :alt="selectedPlatformOption.brand"
                                    class="size-4 shrink-0 rounded-full"
                                />
                                <span class="truncate">{{ selectedPlatformOption.brand }}</span>
                                <span class="text-xs text-muted-foreground">{{ selectedPlatformOption.format }}</span>
                            </span>
                        </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{{ $t('posts.templates.all_platforms') }}</SelectItem>
                        <SelectItem
                            v-for="opt in platformOptions"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            <span class="flex items-center gap-2">
                                <img :src="opt.logo" :alt="opt.brand" class="size-4 shrink-0 rounded-full" />
                                <span class="truncate">{{ opt.brand }}</span>
                                <span class="text-xs text-muted-foreground">{{ opt.format }}</span>
                            </span>
                        </SelectItem>
                    </SelectContent>
                </Select>

                <div class="relative w-full max-w-sm">
                    <IconSearch class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        :placeholder="$t('posts.templates.search_placeholder')"
                        class="pl-9"
                    />
                </div>
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
                        :key="template.slug"
                        class="mb-4 flex break-inside-avoid flex-col rounded-xl border bg-card p-5 transition-colors hover:border-primary/40"
                    >
                        <header class="flex items-center gap-2">
                            <img
                                :src="platformLogo(template.platform)"
                                :alt="platformBrand(template.platform)"
                                class="size-6 shrink-0 rounded-full ring-1 ring-border"
                            />
                            <span class="truncate text-xs font-medium">{{ platformBrand(template.platform) }}</span>
                            <Badge
                                variant="outline"
                                class="ml-auto shrink-0 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                            >
                                {{ formatLabel(template.platform) }}
                            </Badge>
                        </header>

                        <h3 class="mt-3 text-base font-semibold leading-snug tracking-tight">{{ template.name }}</h3>

                        <p
                            v-if="template.description"
                            class="mt-2 text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ template.description }}
                        </p>

                        <div class="mt-3 flex items-center gap-2">
                            <Badge variant="secondary" class="shrink-0 text-xs font-normal">
                                {{ $t(`posts.templates.category.${template.category}`) }}
                            </Badge>
                            <span
                                v-if="template.slides && template.slides.length > 0"
                                class="text-xs text-muted-foreground"
                            >
                                {{ transChoice('posts.templates.slides_count', template.slides.length, { count: template.slides.length }) }}
                            </span>
                        </div>

                        <Button
                            size="sm"
                            variant="outline"
                            class="mt-4 w-full"
                            :disabled="applyingSlug === template.slug"
                            @click="applyTemplate(template)"
                        >
                            <IconLoader2 v-if="applyingSlug === template.slug" class="mr-1 size-3 animate-spin" />
                            {{ applyingSlug === template.slug ? $t('posts.templates.applying') : $t('posts.templates.use_this') }}
                        </Button>
                    </article>
                </div>
            </InfiniteScroll>
        </div>
    </AppLayout>
</template>
