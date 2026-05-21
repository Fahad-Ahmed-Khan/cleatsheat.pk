<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    total: { type: Number, default: 0 },
    activeFilterCount: { type: Number, default: 0 },
    sort: { type: String, default: '' },
    availability: { type: String, default: '' },
    title: { type: String, default: 'Get your next pair' },
    breadcrumbLabel: { type: String, default: 'Shop' },
    breadcrumbParentHref: { type: String, default: '' },
    breadcrumbParentLabel: { type: String, default: '' },
});

const emit = defineEmits([
    'update:sort',
    'update:availability',
    'clear-filters',
    'open-filters',
]);

const sortOptions = [
    { value: '', label: 'Newest' },
    { value: 'price_asc', label: 'Price ↑' },
    { value: 'price_desc', label: 'Price ↓' },
];

function setSort(value) {
    emit('update:sort', value);
}

function toggleInStock() {
    emit('update:availability', props.availability === 'in_stock' ? '' : 'in_stock');
}
</script>

<template>
    <header
        class="relative -mx-5 overflow-hidden rounded-b-2xl border-b border-stadium-outline-soft/50 bg-stadium-white text-stadium-ink sm:-mx-8 lg:-mx-10 dark:border-white/10 dark:bg-stadium-inverse dark:text-stadium-inverse-text"
    >
        <div
            class="pointer-events-none absolute inset-0 bg-gradient-to-r from-stadium-muted/60 via-stadium-white to-store-primary/10 dark:from-stadium-inverse dark:via-stadium-inverse/98 dark:to-store-secondary/25"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute inset-0 store-pitch-pattern opacity-30 dark:opacity-100"
            aria-hidden="true"
        />

        <div class="relative px-5 py-3 sm:px-8 lg:px-10">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 flex-1">
                    <nav
                        class="flex items-center gap-1.5 text-[10px] font-medium text-stadium-secondary dark:text-stadium-inverse-text/55"
                        aria-label="Breadcrumb"
                    >
                        <Link :href="route('store.home')" class="hover:text-store-secondary dark:hover:text-stadium-lime">
                            Home
                        </Link>
                        <template v-if="breadcrumbParentHref">
                            <span aria-hidden="true">/</span>
                            <Link
                                :href="breadcrumbParentHref"
                                class="hover:text-store-secondary dark:hover:text-stadium-lime"
                            >
                                {{ breadcrumbParentLabel }}
                            </Link>
                        </template>
                        <span aria-hidden="true">/</span>
                        <span class="font-semibold text-store-secondary dark:text-stadium-lime">{{ breadcrumbLabel }}</span>
                    </nav>
                    <div class="mt-0.5 flex flex-wrap items-baseline gap-2">
                        <h1 class="font-display text-lg font-extrabold tracking-tight text-stadium-ink sm:text-xl dark:text-white">
                            {{ title }}
                        </h1>
                        <span class="text-[11px] font-semibold tabular-nums text-stadium-secondary dark:text-stadium-inverse-text/65">
                            {{ total }} styles
                        </span>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1.5 lg:hidden">
                    <button
                        type="button"
                        class="mr-1 inline-flex min-h-7 items-center rounded-md px-2.5 text-[10px] font-bold uppercase tracking-wide transition"
                        :class="
                            availability === 'in_stock'
                                ? 'bg-store-primary text-store-primary-fg ring-1 ring-store-primary/50'
                                : 'bg-stadium-muted text-stadium-ink ring-1 ring-stadium-outline-soft hover:bg-stadium-container-high dark:bg-white/10 dark:text-stadium-inverse-text dark:ring-white/15 dark:hover:bg-white/15'
                        "
                        :aria-pressed="availability === 'in_stock'"
                        @click="toggleInStock"
                    >
                        In stock
                    </button>
                    <button
                        type="button"
                        class="mr-1 inline-flex min-h-7 items-center gap-1 rounded-md bg-store-primary px-2.5 text-[10px] font-bold uppercase text-store-primary-fg shadow-sm ring-1 ring-store-primary/40"
                        @click="emit('open-filters')"
                    >
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M4 7h16M4 12h10M4 17h6" stroke-linecap="round" />
                        </svg>
                        Filters
                        <span
                            v-if="activeFilterCount"
                            class="flex h-3.5 min-w-3.5 items-center justify-center rounded-full bg-stadium-ink text-[8px] text-stadium-lime dark:bg-stadium-inverse"
                        >{{ activeFilterCount }}</span>
                    </button>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-1 sm:ml-auto">
                    <button
                        v-if="activeFilterCount"
                        type="button"
                        class="mr-2 hidden text-[10px] font-semibold text-store-secondary hover:underline dark:text-stadium-lime sm:inline-flex"
                        @click="emit('clear-filters')"
                    >
                        Clear ({{ activeFilterCount }})
                    </button>
                    <span class="mr-2 text-[9px] font-bold uppercase tracking-wider text-stadium-secondary dark:text-stadium-inverse-text/45">
                        Sort
                    </span>
                    <div class="flex flex-wrap items-center justify-end" role="group" aria-label="Sort products">
                        <button
                            v-for="opt in sortOptions"
                            :key="opt.value || 'newest'"
                            type="button"
                            class="mr-1.5 inline-flex min-h-6 items-center rounded-md px-2.5 text-[10px] font-semibold leading-none transition last:mr-0"
                            :class="
                                sort === opt.value
                                    ? 'bg-store-primary text-store-primary-fg ring-1 ring-store-primary/40'
                                    : 'bg-stadium-muted text-stadium-ink ring-1 ring-stadium-outline-soft hover:bg-stadium-container-high dark:bg-white/10 dark:text-stadium-inverse-text/90 dark:ring-white/10 dark:hover:bg-white/15'
                            "
                            :aria-pressed="sort === opt.value"
                            @click="setSort(opt.value)"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                    <button
                        type="button"
                        class="mr-1 hidden min-h-6 items-center rounded-md px-2.5 text-[10px] font-bold uppercase tracking-wide transition sm:inline-flex"
                        :class="
                            availability === 'in_stock'
                                ? 'bg-store-secondary text-white ring-1 ring-store-secondary/50 dark:bg-store-primary dark:text-store-primary-fg dark:ring-store-primary/50'
                                : 'bg-stadium-muted text-stadium-ink ring-1 ring-stadium-outline-soft hover:bg-stadium-container-high dark:bg-white/10 dark:text-stadium-inverse-text dark:ring-white/15 dark:hover:bg-white/15'
                        "
                        :aria-pressed="availability === 'in_stock'"
                        @click="toggleInStock"
                    >
                        In stock
                    </button>
                    <button
                        v-if="activeFilterCount"
                        type="button"
                        class="mr-1 text-[10px] font-semibold text-store-secondary hover:underline dark:text-stadium-lime sm:hidden"
                        @click="emit('clear-filters')"
                    >
                        Clear
                    </button>
                </div>
            </div>

            <slot name="below" />
        </div>
    </header>
</template>
