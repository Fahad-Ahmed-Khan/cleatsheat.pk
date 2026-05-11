<script setup>
import StoreBottomSheet from '@/Components/Store/StoreBottomSheet.vue';
import StoreProductCard from '@/Components/Store/StoreProductCard.vue';
import StoreSeoHead from '@/Components/Store/StoreSeoHead.vue';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    products: { type: Array, default: () => [] },
    pagination: { type: Object, required: true },
    filterOptions: { type: Object, required: true },
    filters: { type: Object, required: true },
    seo: { type: Object, required: true },
});

const filterOpen = ref(false);

const local = reactive({
    category_ids: [...(props.filters.category_ids || [])],
    brand_ids: [...(props.filters.brand_ids || [])],
    color_ids: [...(props.filters.color_ids || [])],
    gender: props.filters.gender || '',
    price_min: props.filters.price_min ?? '',
    price_max: props.filters.price_max ?? '',
    size: props.filters.size || '',
    availability: props.filters.availability || '',
    sort: props.filters.sort || '',
});

watch(
    () => props.filters,
    (f) => {
        local.category_ids = [...(f.category_ids || [])];
        local.brand_ids = [...(f.brand_ids || [])];
        local.color_ids = [...(f.color_ids || [])];
        local.gender = f.gender || '';
        local.price_min = f.price_min ?? '';
        local.price_max = f.price_max ?? '';
        local.size = f.size || '';
        local.availability = f.availability || '';
        local.sort = f.sort || '';
    },
    { deep: true },
);

const activeFilterCount = computed(() => {
    let n = 0;
    if (local.category_ids.length) n++;
    if (local.brand_ids.length) n++;
    if (local.color_ids.length) n++;
    if (local.gender) n++;
    if (local.price_min !== '' && local.price_min != null) n++;
    if (local.price_max !== '' && local.price_max != null) n++;
    if (local.size) n++;
    if (local.availability) n++;
    return n;
});

function buildQuery() {
    const q = {};
    if (local.category_ids.length) q.category_ids = local.category_ids;
    if (local.brand_ids.length) q.brand_ids = local.brand_ids;
    if (local.color_ids.length) q.color_ids = local.color_ids;
    if (local.gender) q.gender = local.gender;
    if (local.price_min !== '' && local.price_min != null) q.price_min = local.price_min;
    if (local.price_max !== '' && local.price_max != null) q.price_max = local.price_max;
    if (local.size) q.size = local.size;
    if (local.availability) q.availability = local.availability;
    if (local.sort) q.sort = local.sort;
    return q;
}

function queryString(extra = {}) {
    const merged = { ...buildQuery(), ...extra };
    const p = new URLSearchParams();
    Object.entries(merged).forEach(([key, val]) => {
        if (val === undefined || val === null || val === '') return;
        if (Array.isArray(val)) {
            val.forEach((v) => p.append(`${key}[]`, String(v)));
        } else {
            p.set(key, String(val));
        }
    });
    const s = p.toString();
    return s ? `?${s}` : '';
}

function shopHref(extra = {}) {
    return `${route('store.shop')}${queryString(extra)}`;
}

function applyFilters() {
    router.get(route('store.shop'), buildQuery(), {
        preserveScroll: true,
        replace: true,
    });
    filterOpen.value = false;
}

function clearFilters() {
    local.category_ids = [];
    local.brand_ids = [];
    local.color_ids = [];
    local.gender = '';
    local.price_min = '';
    local.price_max = '';
    local.size = '';
    local.availability = '';
    local.sort = '';
    router.get(route('store.shop'), {}, { preserveScroll: true, replace: true });
    filterOpen.value = false;
}

function toggleArray(arr, id) {
    const i = arr.indexOf(id);
    if (i === -1) arr.push(id);
    else arr.splice(i, 1);
}

function changeSort(value) {
    local.sort = value;
    applyFilters();
}

const genderLabels = {
    men: 'Men',
    women: 'Women',
    unisex: 'Unisex',
    kids: 'Kids',
};

const sortLabel = computed(() => {
    if (local.sort === 'price_asc') return 'Price: low to high';
    if (local.sort === 'price_desc') return 'Price: high to low';
    return 'Newest';
});
</script>

<template>
    <StoreSeoHead :seo="seo" />
    <StoreLayout>
        <div class="mx-auto max-w-6xl pb-28 lg:pb-12">
            <!-- Hero / breadcrumb header -->
            <div class="border-b border-stone-100 bg-white px-4 py-8 sm:px-6 sm:py-10">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-stone-500">
                    Catalogue
                </p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-stone-900 sm:text-3xl">
                    Shop all
                </h1>
                <p class="mt-2 text-sm text-stone-500">
                    {{ pagination.total }} styles · filter by brand, colour, size & price.
                </p>
            </div>

            <!-- Sort + active count strip -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 bg-white px-4 py-3 sm:px-6">
                <div class="flex items-center gap-2 text-xs text-stone-500">
                    <span class="font-medium">Sort by</span>
                    <select
                        v-model="local.sort"
                        class="min-h-9 rounded-full border border-stone-200 bg-white px-3 text-xs font-medium text-stone-800"
                        @change="changeSort(local.sort)"
                    >
                        <option value="">
                            Newest
                        </option>
                        <option value="price_asc">
                            Price: low → high
                        </option>
                        <option value="price_desc">
                            Price: high → low
                        </option>
                    </select>
                </div>
                <p v-if="activeFilterCount" class="text-xs text-stone-500">
                    <span class="font-semibold text-stone-900">{{ activeFilterCount }}</span> filter{{ activeFilterCount === 1 ? '' : 's' }} applied
                    <button
                        type="button"
                        class="ml-2 underline underline-offset-2 hover:text-stone-900"
                        @click="clearFilters"
                    >
                        Clear
                    </button>
                </p>
            </div>

            <div class="lg:grid lg:grid-cols-[minmax(0,260px)_1fr] lg:gap-10 lg:px-6 lg:pt-8">
                <!-- Desktop filters -->
                <aside class="hidden lg:block">
                    <div class="sticky top-24 space-y-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200/80">
                        <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">
                            Filters
                        </p>

                        <div v-if="filterOptions.categories?.length">
                            <p class="text-xs font-medium text-stone-700">
                                Category
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="c in filterOptions.categories"
                                    :key="c.id"
                                    type="button"
                                    class="min-h-10 rounded-full px-3 py-2 text-xs font-medium transition"
                                    :class="
                                        local.category_ids.includes(c.id)
                                            ? 'bg-stone-900 text-white'
                                            : 'bg-stone-50 text-stone-700 ring-1 ring-stone-200 hover:bg-stone-100'
                                    "
                                    @click="toggleArray(local.category_ids, c.id)"
                                >
                                    {{ c.name }}
                                </button>
                            </div>
                        </div>

                        <div v-if="filterOptions.brands?.length">
                            <p class="text-xs font-medium text-stone-700">
                                Brand
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="b in filterOptions.brands"
                                    :key="b.id"
                                    type="button"
                                    class="min-h-10 rounded-full px-3 py-2 text-xs font-medium transition"
                                    :class="
                                        local.brand_ids.includes(b.id)
                                            ? 'bg-stone-900 text-white'
                                            : 'bg-stone-50 text-stone-700 ring-1 ring-stone-200 hover:bg-stone-100'
                                    "
                                    @click="toggleArray(local.brand_ids, b.id)"
                                >
                                    {{ b.name }}
                                </button>
                            </div>
                        </div>

                        <div v-if="filterOptions.colors?.length">
                            <p class="text-xs font-medium text-stone-700">
                                Colour
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="c in filterOptions.colors"
                                    :key="c.id"
                                    type="button"
                                    class="flex min-h-10 items-center gap-2 rounded-full py-1.5 pl-1.5 pr-3 text-xs font-medium transition"
                                    :class="
                                        local.color_ids.includes(c.id)
                                            ? 'bg-stone-900 text-white'
                                            : 'bg-stone-50 text-stone-700 ring-1 ring-stone-200'
                                    "
                                    @click="toggleArray(local.color_ids, c.id)"
                                >
                                    <span class="h-6 w-6 rounded-full ring-2 ring-white/30" :style="{ backgroundColor: c.hex }" />
                                    {{ c.name }}
                                </button>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-stone-700">
                                Gender
                            </p>
                            <select v-model="local.gender" class="mt-2 w-full min-h-11 rounded-xl border border-stone-200 bg-white px-3 text-sm">
                                <option value="">
                                    Any
                                </option>
                                <option v-for="g in filterOptions.genders" :key="g" :value="g">
                                    {{ genderLabels[g] ?? g }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-stone-700">
                                Price (PKR)
                            </p>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <input
                                    v-model.number="local.price_min"
                                    type="number"
                                    placeholder="Min"
                                    class="min-h-11 rounded-xl border border-stone-200 px-2 text-sm"
                                >
                                <input
                                    v-model.number="local.price_max"
                                    type="number"
                                    placeholder="Max"
                                    class="min-h-11 rounded-xl border border-stone-200 px-2 text-sm"
                                >
                            </div>
                            <p class="mt-1 text-[11px] text-stone-400">
                                Range {{ Math.round(filterOptions.price_min) }} – {{ Math.round(filterOptions.price_max) }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-stone-700">
                                Size label
                            </p>
                            <select v-model="local.size" class="mt-2 w-full min-h-11 rounded-xl border border-stone-200 px-3 text-sm">
                                <option value="">
                                    Any
                                </option>
                                <option v-for="s in filterOptions.sizes" :key="s" :value="s">
                                    {{ s }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-stone-700">
                                Availability
                            </p>
                            <select v-model="local.availability" class="mt-2 w-full min-h-11 rounded-xl border border-stone-200 px-3 text-sm">
                                <option value="">
                                    All
                                </option>
                                <option value="in_stock">
                                    In stock only
                                </option>
                            </select>
                        </div>

                        <div class="flex flex-col gap-2">
                            <button
                                type="button"
                                class="min-h-11 rounded-xl bg-stone-900 text-sm font-semibold text-white transition active:scale-[0.99]"
                                @click="applyFilters"
                            >
                                Apply
                            </button>
                            <button type="button" class="text-xs font-medium text-stone-500 underline" @click="clearFilters">
                                Clear all
                            </button>
                        </div>
                    </div>
                </aside>

                <!-- Grid -->
                <div class="px-4 pt-6 sm:px-0">
                    <div v-if="!products.length" class="rounded-2xl bg-stone-50 py-16 text-center text-sm text-stone-600">
                        No shoes match these filters.
                        <button type="button" class="mt-2 block w-full font-semibold text-stone-900 underline sm:inline" @click="clearFilters">
                            Reset filters
                        </button>
                    </div>
                    <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                        <StoreProductCard v-for="(p, i) in products" :key="p.id" :product="p" :index="i" />
                    </div>

                    <div
                        v-if="pagination.last_page > 1"
                        class="mt-12 flex items-center justify-center gap-3"
                    >
                        <Link
                            v-if="pagination.current_page > 1"
                            :href="shopHref({ page: pagination.current_page - 1 })"
                            preserve-scroll
                            class="min-h-11 rounded-full bg-white px-5 py-2.5 text-sm font-semibold shadow-sm ring-1 ring-stone-200 transition hover:ring-stone-300"
                        >
                            Previous
                        </Link>
                        <span class="text-sm tabular-nums text-stone-500">
                            {{ pagination.current_page }} / {{ pagination.last_page }}
                        </span>
                        <Link
                            v-if="pagination.current_page < pagination.last_page"
                            :href="shopHref({ page: pagination.current_page + 1 })"
                            preserve-scroll
                            class="min-h-11 rounded-full bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white shadow-md"
                        >
                            Next
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile filter FAB -->
        <div class="fixed bottom-24 left-4 z-40 lg:hidden">
            <button
                type="button"
                class="flex min-h-12 items-center gap-2 rounded-full bg-stone-900 pl-5 pr-5 text-sm font-semibold text-white shadow-lg ring-2 ring-white/90 transition active:scale-[0.98]"
                @click="filterOpen = true"
            >
                Filters
                <span
                    v-if="activeFilterCount"
                    class="flex h-6 min-w-6 items-center justify-center rounded-full bg-emerald-500 px-1.5 text-[11px] font-bold"
                >{{ activeFilterCount }}</span>
            </button>
        </div>

        <StoreBottomSheet :open="filterOpen" title="Filters" @close="filterOpen = false">
            <div class="mt-4 space-y-6">
                <div v-if="filterOptions.categories?.length">
                    <p class="text-xs font-semibold uppercase text-stone-500">
                        Category
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="c in filterOptions.categories"
                            :key="'m-cat-' + c.id"
                            type="button"
                            class="min-h-11 rounded-full px-4 py-2 text-sm font-medium"
                            :class="
                                local.category_ids.includes(c.id)
                                    ? 'bg-stone-900 text-white'
                                    : 'bg-stone-50 text-stone-800 ring-1 ring-stone-200'
                            "
                            @click="toggleArray(local.category_ids, c.id)"
                        >
                            {{ c.name }}
                        </button>
                    </div>
                </div>
                <div v-if="filterOptions.brands?.length">
                    <p class="text-xs font-semibold uppercase text-stone-500">
                        Brand
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="b in filterOptions.brands"
                            :key="'m-b-' + b.id"
                            type="button"
                            class="min-h-11 rounded-full px-4 py-2 text-sm font-medium"
                            :class="
                                local.brand_ids.includes(b.id)
                                    ? 'bg-stone-900 text-white'
                                    : 'bg-stone-50 text-stone-800 ring-1 ring-stone-200'
                            "
                            @click="toggleArray(local.brand_ids, b.id)"
                        >
                            {{ b.name }}
                        </button>
                    </div>
                </div>
                <div v-if="filterOptions.colors?.length">
                    <p class="text-xs font-semibold uppercase text-stone-500">
                        Colour
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="c in filterOptions.colors"
                            :key="'m-c-' + c.id"
                            type="button"
                            class="flex min-h-11 items-center gap-2 rounded-full py-1.5 pl-2 pr-4 text-sm"
                            :class="
                                local.color_ids.includes(c.id)
                                    ? 'bg-stone-900 text-white'
                                    : 'bg-stone-50 ring-1 ring-stone-200'
                            "
                            @click="toggleArray(local.color_ids, c.id)"
                        >
                            <span class="h-7 w-7 rounded-full ring-2 ring-white/40" :style="{ backgroundColor: c.hex }" />
                            {{ c.name }}
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-medium text-stone-600">Gender</label>
                        <select v-model="local.gender" class="mt-1 w-full min-h-12 rounded-xl border border-stone-200 px-2 text-sm">
                            <option value="">
                                Any
                            </option>
                            <option v-for="g in filterOptions.genders" :key="'mg-' + g" :value="g">
                                {{ genderLabels[g] ?? g }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-stone-600">Availability</label>
                        <select v-model="local.availability" class="mt-1 w-full min-h-12 rounded-xl border border-stone-200 px-2 text-sm">
                            <option value="">
                                All
                            </option>
                            <option value="in_stock">
                                In stock
                            </option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-medium text-stone-600">Size</label>
                    <select v-model="local.size" class="mt-1 w-full min-h-12 rounded-xl border border-stone-200 px-2 text-sm">
                        <option value="">
                            Any
                        </option>
                        <option v-for="s in filterOptions.sizes" :key="'ms-' + s" :value="s">
                            {{ s }}
                        </option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-medium text-stone-600">Min price</label>
                        <input v-model.number="local.price_min" type="number" class="mt-1 w-full min-h-12 rounded-xl border border-stone-200 px-3 text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-stone-600">Max price</label>
                        <input v-model.number="local.price_max" type="number" class="mt-1 w-full min-h-12 rounded-xl border border-stone-200 px-3 text-sm">
                    </div>
                </div>
                <div class="flex gap-3 pt-2">
                    <button
                        type="button"
                        class="min-h-12 flex-1 rounded-2xl bg-stone-900 text-sm font-semibold text-white"
                        @click="applyFilters"
                    >
                        Show results
                    </button>
                    <button type="button" class="min-h-12 rounded-2xl px-4 text-sm font-medium text-stone-600 ring-1 ring-stone-200" @click="clearFilters">
                        Clear
                    </button>
                </div>
            </div>
        </StoreBottomSheet>
    </StoreLayout>
</template>
