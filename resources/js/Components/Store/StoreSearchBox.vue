<script setup>
import { useStoreSearchSuggest } from '@/composables/useStoreSearchSuggest';
import { router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    compact: { type: Boolean, default: false },
    initialQuery: { type: String, default: '' },
});

const inputRef = ref(null);
const query = ref(props.initialQuery);
const listboxId = 'store-search-suggestions';

const {
    suggestions,
    loading,
    open,
    activeIndex,
    scheduleSuggest,
    close,
    moveActive,
    flattenItems,
} = useStoreSearchSuggest();

const flatItems = computed(() => flattenItems(suggestions.value));
const debounceMs = 300;
const minLength = 2;

watch(
    () => props.initialQuery,
    (v) => {
        if (v !== query.value) {
            query.value = v;
        }
    },
);

function submitSearch(term) {
    const q = (term ?? query.value).trim();
    close();
    if (!q) {
        router.visit(route('store.shop'));
        return;
    }
    router.visit(route('store.search', { q }));
}

function onInput() {
    scheduleSuggest(query.value, debounceMs, minLength);
}

function onFocus() {
    if (flatItems.value.length) {
        open.value = true;
    } else if (query.value.trim().length >= minLength) {
        scheduleSuggest(query.value, 0, minLength);
    }
}

function onKeydown(e) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (!open.value && flatItems.value.length) {
            open.value = true;
        }
        moveActive(1, flatItems.value);
        return;
    }
    if (e.key === 'ArrowUp') {
        e.preventDefault();
        moveActive(-1, flatItems.value);
        return;
    }
    if (e.key === 'Escape') {
        close();
        return;
    }
    if (e.key === 'Enter') {
        e.preventDefault();
        if (activeIndex.value >= 0 && flatItems.value[activeIndex.value]) {
            selectItem(flatItems.value[activeIndex.value]);
        } else {
            submitSearch();
        }
    }
}

function selectItem(item) {
    if (item.type === 'product') {
        router.visit(route('store.product', { slug: item.slug }));
        close();
        return;
    }
    if (item.type === 'brand') {
        router.visit(route('store.search', { q: item.name }));
        close();
        return;
    }
    if (item.type === 'category') {
        router.visit(route('store.category', { slug: item.slug }));
        close();
        return;
    }
    if (item.type === 'term') {
        submitSearch(item.label);
    }
}

function itemId(index) {
    return `${listboxId}-option-${index}`;
}

function onClickOutside(e) {
    if (inputRef.value && !inputRef.value.contains(e.target)) {
        close();
    }
}

onMounted(() => {
    document.addEventListener('click', onClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', onClickOutside);
});

function formatPrice(value) {
    if (value == null) return '';
    return new Intl.NumberFormat('en-PK', { style: 'currency', currency: 'PKR', maximumFractionDigits: 0 }).format(value);
}
</script>

<template>
    <div ref="inputRef" class="relative" :class="compact ? 'w-full' : 'hidden md:block md:w-56 lg:w-72'">
        <form class="relative" role="search" @submit.prevent="submitSearch()">
            <label :for="compact ? 'store-search-mobile' : 'store-search-desktop'" class="sr-only">
                Search products
            </label>
            <input
                :id="compact ? 'store-search-mobile' : 'store-search-desktop'"
                v-model="query"
                type="search"
                name="q"
                autocomplete="off"
                :placeholder="compact ? 'Search boots, brands…' : 'Search boots, brands…'"
                class="w-full rounded-xl border border-stadium-outline-soft bg-stadium-muted/60 py-2 pl-10 pr-3 text-sm text-stadium-ink placeholder:text-stadium-secondary focus:border-stadium-ink focus:bg-stadium-white focus:outline-none focus:ring-1 focus:ring-stadium-ink dark:border-white/10 dark:bg-stadium-inverse/40 dark:text-stadium-inverse-text"
                role="combobox"
                :aria-expanded="open ? 'true' : 'false'"
                aria-controls="store-search-suggestions"
                :aria-activedescendant="activeIndex >= 0 ? itemId(activeIndex) : undefined"
                @input="onInput"
                @focus="onFocus"
                @keydown="onKeydown"
            >
            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-stadium-secondary" aria-hidden="true">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="7" />
                    <path d="M20 20l-3-3" stroke-linecap="round" />
                </svg>
            </span>
            <span v-if="loading" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                <span class="block h-3 w-3 animate-spin rounded-full border-2 border-stadium-outline border-t-stadium-ink" />
            </span>
        </form>

        <ul
            v-if="open && flatItems.length"
            id="store-search-suggestions"
            role="listbox"
            class="absolute z-50 mt-1 max-h-[min(70vh,24rem)] w-full overflow-y-auto rounded-2xl border border-stadium-outline-soft bg-stadium-white py-2 shadow-stadium-lg dark:border-white/10 dark:bg-stadium-container"
        >
            <li
                v-for="(item, index) in flatItems"
                :id="itemId(index)"
                :key="`${item.type}-${item.id ?? item.slug ?? item.label}-${index}`"
                role="option"
                :aria-selected="activeIndex === index"
                class="cursor-pointer px-3 py-2 text-sm"
                :class="activeIndex === index ? 'bg-stadium-muted' : 'hover:bg-stadium-muted/70'"
                @mousedown.prevent="selectItem(item)"
            >
                <template v-if="item.type === 'product'">
                    <div class="flex items-center gap-3">
                        <img
                            v-if="item.thumbnail"
                            :src="item.thumbnail"
                            alt=""
                            class="h-10 w-8 shrink-0 rounded-lg object-cover"
                            loading="lazy"
                        >
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-stadium-ink">{{ item.name }}</p>
                            <p class="truncate text-xs text-stadium-secondary">
                                {{ item.brand?.name }}
                                <span v-if="item.price_from"> · {{ formatPrice(item.price_from) }}</span>
                            </p>
                        </div>
                    </div>
                </template>
                <template v-else-if="item.type === 'brand'">
                    <span class="text-stadium-secondary">Brand · </span>
                    <span class="font-medium text-stadium-ink">{{ item.name }}</span>
                </template>
                <template v-else-if="item.type === 'category'">
                    <span class="text-stadium-secondary">Category · </span>
                    <span class="font-medium text-stadium-ink">{{ item.name }}</span>
                </template>
                <template v-else>
                    <span class="text-stadium-secondary">Search · </span>
                    <span class="font-medium text-stadium-ink">{{ item.label }}</span>
                </template>
            </li>
        </ul>
    </div>

    <!-- Mobile icon trigger when not compact -->
    <a
        v-if="!compact"
        :href="route('store.search')"
        class="flex items-center justify-center rounded-xl p-2 transition hover:bg-stadium-muted active:scale-95 md:hidden"
        aria-label="Search catalogue"
    >
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="7" />
            <path d="M20 20l-3-3" stroke-linecap="round" />
        </svg>
    </a>
</template>
