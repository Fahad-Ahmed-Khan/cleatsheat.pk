<script setup>
import StoreBottomSheet from '@/Components/Store/StoreBottomSheet.vue';
import StoreProductCard from '@/Components/Store/StoreProductCard.vue';
import StoreProductQuickAddSheet from '@/Components/Store/StoreProductQuickAddSheet.vue';
import StoreShopHeader from '@/Components/Store/StoreShopHeader.vue';
import StoreSeoHead from '@/Components/Store/StoreSeoHead.vue';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { useStoreQuickAdd } from '@/composables/useStoreQuickAdd';
import { Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, onUnmounted, provide, reactive, ref, watch } from 'vue';

const props = defineProps({
    category: { type: Object, required: true },
    products: { type: Array, default: () => [] },
    pagination: { type: Object, required: true },
    filterOptions: { type: Object, required: true },
    filters: { type: Object, required: true },
    seo: { type: Object, required: true },
});

const filterOpen = ref(false);

const {
    sheetOpen,
    sheetProduct,
    adding,
    quickAdd,
    addWithSize,
    closeSheet,
} = useStoreQuickAdd();

provide('storeQuickAdd', { quickAdd });

onUnmounted(closeSheet);

function onSheetSelect({ variantId, sizeLabel }) {
    if (!sheetProduct.value) return;
    addWithSize(sheetProduct.value, variantId, sizeLabel);
}

// Filters intentionally hidden from the storefront for now: color, gender.
const local = reactive({
    brand_ids: [...(props.filters.brand_ids || [])],
    color_ids: [...(props.filters.color_ids || [])],
    gender: props.filters.gender || '',
    price_min: props.filters.price_min ?? '',
    price_max: props.filters.price_max ?? '',
    size_uk: Array.isArray(props.filters.size_uk) ? [...props.filters.size_uk] : (props.filters.size_uk ? [String(props.filters.size_uk)] : []),
    availability: props.filters.availability || '',
    sort: props.filters.sort || '',
});

const childCategories = computed(() => props.category.children ?? []);

const activeFilterCount = computed(() => {
    let n = 0;
    if (local.brand_ids.length) n++;
    if (local.size_uk.length) n++;
    if (local.price_min !== '' && local.price_min != null) n++;
    if (local.price_max !== '' && local.price_max != null) n++;
    if (local.availability) n++;
    return n;
});

function buildQuery() {
    const q = {};
    if (local.brand_ids.length) q.brand_ids = local.brand_ids;
    if (local.color_ids.length) q.color_ids = local.color_ids;
    if (local.gender) q.gender = local.gender;
    if (local.price_min !== '' && local.price_min != null) q.price_min = local.price_min;
    if (local.price_max !== '' && local.price_max != null) q.price_max = local.price_max;
    if (local.size_uk.length) q.size_uk = local.size_uk;
    if (local.availability) q.availability = local.availability;
    if (local.sort) q.sort = local.sort;
    return q;
}

const feedItems = ref([...props.products]);
const feedPage = ref(props.pagination.current_page || 1);
const feedLastPage = ref(props.pagination.last_page || 1);
const feedTotal = ref(props.pagination.total || feedItems.value.length);
const loadingMore = ref(false);

const hasMore = computed(() => feedPage.value < feedLastPage.value);

function resetFeed() {
    feedItems.value = [...props.products];
    feedPage.value = props.pagination.current_page || 1;
    feedLastPage.value = props.pagination.last_page || 1;
    feedTotal.value = props.pagination.total || feedItems.value.length;
}

function filterSignature() {
    return JSON.stringify({
        b: [...local.brand_ids].sort((a, b) => a - b),
        s: [...local.size_uk].slice().sort(),
        a: local.availability || '',
        pmin: local.price_min === '' || local.price_min == null ? null : Number(local.price_min),
        pmax: local.price_max === '' || local.price_max == null ? null : Number(local.price_max),
        sort: local.sort || '',
    });
}

let lastAppliedSignature = filterSignature();
let autoApplyTimer = null;

const categoryRoute = () => route('store.category', props.category.slug);

function performApply() {
    router.get(categoryRoute(), buildQuery(), {
        preserveScroll: true,
        replace: true,
    });
}

function scheduleAutoApply(delay = 180) {
    const sig = filterSignature();
    if (sig === lastAppliedSignature) return;
    if (autoApplyTimer) clearTimeout(autoApplyTimer);
    autoApplyTimer = setTimeout(() => {
        autoApplyTimer = null;
        const current = filterSignature();
        if (current === lastAppliedSignature) return;
        lastAppliedSignature = current;
        performApply();
    }, delay);
}

watch(
    () => props.filters,
    (f) => {
        local.brand_ids = [...(f.brand_ids || [])];
        local.color_ids = [...(f.color_ids || [])];
        local.gender = f.gender || '';
        local.price_min = f.price_min ?? '';
        local.price_max = f.price_max ?? '';
        local.size_uk = Array.isArray(f.size_uk) ? [...f.size_uk] : (f.size_uk ? [String(f.size_uk)] : []);
        local.availability = f.availability || '';
        local.sort = f.sort || '';
        lastAppliedSignature = filterSignature();
        resetFeed();
    },
    { deep: true },
);

watch(
    () => [
        [...local.brand_ids].sort((a, b) => a - b).join(','),
        [...local.size_uk].slice().sort().join(','),
        local.availability,
        local.sort,
    ],
    () => scheduleAutoApply(180),
);

watch(
    () => [local.price_min, local.price_max],
    () => scheduleAutoApply(550),
);

function clearFilters() {
    local.brand_ids = [];
    local.color_ids = [];
    local.gender = '';
    local.price_min = '';
    local.price_max = '';
    local.size_uk = [];
    local.availability = '';
    local.sort = '';
    if (autoApplyTimer) {
        clearTimeout(autoApplyTimer);
        autoApplyTimer = null;
    }
    lastAppliedSignature = filterSignature();
    router.get(categoryRoute(), {}, { preserveScroll: true, replace: true });
    filterOpen.value = false;
}

function toggleArray(arr, id) {
    const i = arr.indexOf(id);
    if (i === -1) arr.push(id);
    else arr.splice(i, 1);
}

function loadMore() {
    if (loadingMore.value || !hasMore.value) return;
    loadingMore.value = true;
    const nextPage = feedPage.value + 1;

    router.get(
        categoryRoute(),
        { ...buildQuery(), page: nextPage },
        {
            only: ['products', 'pagination'],
            preserveScroll: true,
            preserveState: true,
            preserveUrl: true,
            replace: true,
            onSuccess: (page) => {
                const newItems = page?.props?.products ?? props.products ?? [];
                const newPagination = page?.props?.pagination ?? props.pagination ?? {};
                feedItems.value = feedItems.value.concat(newItems);
                feedPage.value = newPagination.current_page || nextPage;
                feedLastPage.value = newPagination.last_page || feedLastPage.value;
                feedTotal.value = newPagination.total || feedTotal.value;
            },
            onFinish: () => {
                loadingMore.value = false;
            },
        },
    );
}

const sentinel = ref(null);
let observer = null;

onMounted(() => {
    if (typeof window === 'undefined' || !('IntersectionObserver' in window)) return;
    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    loadMore();
                }
            }
        },
        { rootMargin: '600px 0px' },
    );
    if (sentinel.value) observer.observe(sentinel.value);
});

onBeforeUnmount(() => {
    if (observer) observer.disconnect();
});

watch(sentinel, (el) => {
    if (!observer) return;
    observer.disconnect();
    if (el) observer.observe(el);
});

const ukSizes = computed(() => props.filterOptions.sizes_uk || []);
</script>

<template>
    <StoreSeoHead :seo="seo" />
    <StoreLayout>
        <div class="store-container pb-28 lg:pb-12">
            <StoreShopHeader
                :title="category.name"
                :breadcrumb-label="category.name"
                :breadcrumb-parent-href="route('store.shop')"
                breadcrumb-parent-label="Shop"
                :total="feedTotal"
                :active-filter-count="activeFilterCount"
                :sort="local.sort"
                :availability="local.availability"
                @update:sort="local.sort = $event"
                @update:availability="local.availability = $event"
                @clear-filters="clearFilters"
                @open-filters="filterOpen = true"
            >
                <template #below>
                    <div
                        v-if="childCategories.length"
                        class="no-scrollbar mt-3 flex gap-2 overflow-x-auto pb-1"
                    >
                        <Link
                            :href="route('store.category', category.slug)"
                            class="shrink-0 rounded-full border border-store-primary bg-store-primary/15 px-3.5 py-1.5 text-[10px] font-bold uppercase tracking-wide text-stadium-ink ring-1 ring-store-primary/30 dark:text-stadium-inverse-text"
                        >
                            All {{ category.name }}
                        </Link>
                        <Link
                            v-for="ch in childCategories"
                            :key="ch.id"
                            :href="route('store.category', ch.slug)"
                            class="shrink-0 rounded-full border border-stadium-outline-soft bg-stadium-muted px-3.5 py-1.5 text-[10px] font-bold uppercase tracking-wide text-stadium-ink transition hover:border-store-primary hover:bg-stadium-white dark:bg-white/10 dark:text-stadium-inverse-text dark:hover:bg-white/15"
                        >
                            {{ ch.name }}
                        </Link>
                    </div>
                    <div
                        v-if="category.intro_html"
                        class="prose prose-sm prose-stone mx-auto mt-3 max-w-3xl leading-relaxed dark:prose-invert"
                        v-html="category.intro_html"
                    />
                </template>
            </StoreShopHeader>

            <div class="lg:grid lg:grid-cols-[minmax(0,220px)_1fr] lg:gap-5 lg:pt-6">
                <!-- Desktop filters -->
                <aside class="hidden lg:block">
                    <div class="sticky top-24 space-y-6 rounded-3xl bg-stadium-white p-5 shadow-sm ring-1 ring-stadium-outline-soft/80">
                        <p class="text-xs font-semibold uppercase tracking-wide text-stadium-secondary">
                            Filters
                        </p>

                        <div v-if="ukSizes.length">
                            <p class="text-xs font-medium text-stadium-ink">
                                UK Size
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="s in ukSizes"
                                    :key="`d-uk-${s}`"
                                    type="button"
                                    class="min-h-10 min-w-[42px] rounded-full px-3 py-2 text-xs font-semibold tabular-nums transition"
                                    :class="
                                        local.size_uk.includes(s)
                                            ? 'bg-store-primary text-store-primary-fg ring-2 ring-store-primary/40 shadow-sm'
                                            : 'bg-stadium-muted text-stadium-ink ring-1 ring-stadium-outline-soft hover:bg-stadium-container-high'
                                    "
                                    :aria-pressed="local.size_uk.includes(s)"
                                    @click="toggleArray(local.size_uk, s)"
                                >
                                    UK {{ s }}
                                </button>
                            </div>
                        </div>

                        <div v-if="filterOptions.brands?.length">
                            <p class="text-xs font-medium text-stadium-ink">
                                Brand
                            </p>
                            <ul class="mt-2 space-y-1.5">
                                <li v-for="b in filterOptions.brands" :key="`d-b-${b.id}`">
                                    <label class="flex min-h-9 cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm text-stadium-ink hover:bg-stadium-muted">
                                        <input
                                            type="checkbox"
                                            :checked="local.brand_ids.includes(b.id)"
                                            class="h-4 w-4 rounded border-stadium-outline-soft bg-stadium-white text-stadium-lime focus:ring-stadium-ink"
                                            @change="toggleArray(local.brand_ids, b.id)"
                                        >
                                        <span class="truncate">{{ b.name }}</span>
                                    </label>
                                </li>
                            </ul>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-stadium-ink">
                                Price (PKR)
                            </p>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <input
                                    v-model.number="local.price_min"
                                    type="number"
                                    placeholder="Min"
                                    class="min-h-11 rounded-xl border border-stadium-outline-soft bg-stadium-white px-2 text-sm text-stadium-ink placeholder:text-stadium-secondary focus:border-stadium-ink focus:ring-1 focus:ring-stadium-ink"
                                >
                                <input
                                    v-model.number="local.price_max"
                                    type="number"
                                    placeholder="Max"
                                    class="min-h-11 rounded-xl border border-stadium-outline-soft bg-stadium-white px-2 text-sm text-stadium-ink placeholder:text-stadium-secondary focus:border-stadium-ink focus:ring-1 focus:ring-stadium-ink"
                                >
                            </div>
                            <p class="mt-1 text-[11px] text-stadium-outline">
                                Range {{ Math.round(filterOptions.price_min) }} – {{ Math.round(filterOptions.price_max) }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-stadium-ink">
                                Availability
                            </p>
                            <select v-model="local.availability" class="mt-2 w-full min-h-11 rounded-xl border border-stadium-outline-soft bg-stadium-white px-3 text-sm text-stadium-ink">
                                <option value="">
                                    All
                                </option>
                                <option value="in_stock">
                                    In stock only
                                </option>
                            </select>
                        </div>

                        <div v-if="activeFilterCount" class="flex flex-col gap-2 border-t border-stadium-outline-soft/40 pt-4">
                            <button type="button" class="text-xs font-semibold text-stadium-secondary underline underline-offset-2 hover:text-stadium-ink" @click="clearFilters">
                                Clear all filters
                            </button>
                        </div>
                    </div>
                </aside>

                <!-- Grid -->
                <div>
                    <div v-if="!feedItems.length" class="bg-stadium-muted py-16 text-center text-sm text-stadium-secondary">
                        <p class="text-sm font-medium text-stadium-ink">
                            No shoes match these filters.
                        </p>
                        <p class="mt-1 text-xs text-stadium-secondary">
                            Try removing a filter, or browse the full category.
                        </p>
                        <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-center">
                            <button
                                type="button"
                                class="min-h-11 rounded-full bg-stadium-white px-5 py-2.5 text-sm font-semibold text-stadium-ink shadow-sm ring-1 ring-stadium-outline-soft transition hover:ring-stadium-outline"
                                @click="clearFilters"
                            >
                                Reset filters
                            </button>
                            <Link
                                :href="route('store.category', category.slug)"
                                class="min-h-11 rounded-full bg-stadium-lime px-5 py-2.5 text-sm font-bold text-stadium-lime-ink shadow-md"
                            >
                                View all {{ category.name }}
                            </Link>
                        </div>
                    </div>
                    <div v-else class="store-product-grid--sidebar">
                        <StoreProductCard v-for="(p, i) in feedItems" :key="p.id" :product="p" :index="i" />
                    </div>

                    <div v-if="feedItems.length" class="mt-10 flex flex-col items-center justify-center gap-3">
                        <div
                            v-if="hasMore"
                            ref="sentinel"
                            class="flex h-1 w-full"
                            aria-hidden="true"
                        />
                        <div v-if="loadingMore" class="flex items-center gap-2 text-xs text-stadium-secondary">
                            <span class="h-2 w-2 animate-pulse rounded-full bg-stadium-ink" />
                            <span class="h-2 w-2 animate-pulse rounded-full bg-stadium-ink [animation-delay:120ms]" />
                            <span class="h-2 w-2 animate-pulse rounded-full bg-stadium-ink [animation-delay:240ms]" />
                            <span class="font-medium">Loading more…</span>
                        </div>
                        <button
                            v-else-if="hasMore"
                            type="button"
                            class="min-h-11 rounded-full bg-stadium-white px-5 py-2.5 text-sm font-semibold text-stadium-ink shadow-sm ring-1 ring-stadium-outline-soft transition hover:ring-stadium-outline"
                            @click="loadMore"
                        >
                            Load more
                        </button>
                        <p v-else class="text-xs font-medium uppercase tracking-wide text-stadium-secondary">
                            You've reached the end · {{ feedItems.length }} of {{ feedTotal }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile filter FAB -->
        <div class="fixed bottom-24 left-4 z-40 lg:hidden">
            <button
                type="button"
                class="flex min-h-12 items-center gap-2 rounded-full bg-stadium-inverse pl-5 pr-5 text-sm font-semibold text-stadium-inverse-text shadow-lg ring-2 ring-store-primary/35 transition active:scale-[0.98]"
                @click="filterOpen = true"
            >
                Filters
                <span
                    v-if="activeFilterCount"
                    class="flex h-6 min-w-6 items-center justify-center rounded-full bg-store-primary px-1.5 text-[11px] font-bold text-store-primary-fg"
                >{{ activeFilterCount }}</span>
            </button>
        </div>

        <StoreBottomSheet :open="filterOpen" title="Filters" @close="filterOpen = false">
            <div class="mt-4 space-y-6">
                <div v-if="ukSizes.length">
                    <p class="text-xs font-semibold uppercase text-stadium-secondary">
                        UK Size
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="s in ukSizes"
                            :key="`m-uk-${s}`"
                            type="button"
                            class="min-h-11 min-w-[52px] rounded-full px-4 py-2 text-sm font-semibold tabular-nums"
                            :class="
                                local.size_uk.includes(s)
                                    ? 'bg-store-primary text-store-primary-fg ring-2 ring-store-primary/40'
                                    : 'bg-stadium-muted text-stadium-ink ring-1 ring-stadium-outline-soft'
                            "
                            :aria-pressed="local.size_uk.includes(s)"
                            @click="toggleArray(local.size_uk, s)"
                        >
                            UK {{ s }}
                        </button>
                    </div>
                </div>
                <div v-if="filterOptions.brands?.length">
                    <p class="text-xs font-semibold uppercase text-stadium-secondary">
                        Brand
                    </p>
                    <ul class="mt-2 space-y-1.5">
                        <li v-for="b in filterOptions.brands" :key="`m-b-${b.id}`">
                            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-2 py-1.5 text-sm text-stadium-ink hover:bg-stadium-muted">
                                <input
                                    type="checkbox"
                                    :checked="local.brand_ids.includes(b.id)"
                                    class="h-5 w-5 rounded border-stadium-outline-soft bg-stadium-white text-stadium-lime focus:ring-stadium-ink"
                                    @change="toggleArray(local.brand_ids, b.id)"
                                >
                                <span class="truncate">{{ b.name }}</span>
                            </label>
                        </li>
                    </ul>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-medium text-stadium-secondary">Min price</label>
                        <input v-model.number="local.price_min" type="number" class="mt-1 w-full min-h-12 rounded-xl border border-stadium-outline-soft bg-stadium-white px-3 text-sm text-stadium-ink placeholder:text-stadium-secondary focus:border-stadium-ink focus:ring-1 focus:ring-stadium-ink">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-stadium-secondary">Max price</label>
                        <input v-model.number="local.price_max" type="number" class="mt-1 w-full min-h-12 rounded-xl border border-stadium-outline-soft bg-stadium-white px-3 text-sm text-stadium-ink placeholder:text-stadium-secondary focus:border-stadium-ink focus:ring-1 focus:ring-stadium-ink">
                    </div>
                </div>
                <div>
                    <label class="text-xs font-medium text-stadium-secondary">Availability</label>
                    <select v-model="local.availability" class="mt-1 w-full min-h-12 rounded-xl border border-stadium-outline-soft bg-stadium-white px-2 text-sm text-stadium-ink">
                        <option value="">
                            All
                        </option>
                        <option value="in_stock">
                            In stock
                        </option>
                    </select>
                </div>
                <div class="flex gap-3 pt-2">
                    <button
                        type="button"
                        class="min-h-12 flex-1 rounded-2xl bg-stadium-lime text-sm font-bold text-stadium-lime-ink"
                        @click="filterOpen = false"
                    >
                        Show {{ feedTotal }} result{{ feedTotal === 1 ? '' : 's' }}
                    </button>
                    <button type="button" class="min-h-12 rounded-2xl px-4 text-sm font-medium text-stadium-secondary ring-1 ring-stadium-outline-soft" @click="clearFilters">
                        Clear
                    </button>
                </div>
            </div>
        </StoreBottomSheet>

        <StoreProductQuickAddSheet
            :open="sheetOpen"
            :product="sheetProduct"
            :adding="adding"
            @close="closeSheet"
            @select="onSheetSelect"
        />
    </StoreLayout>
</template>
