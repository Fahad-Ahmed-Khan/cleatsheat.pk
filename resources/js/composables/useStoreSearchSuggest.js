import { onBeforeUnmount, ref } from 'vue';

/**
 * Debounced storefront search autocomplete against GET /search/suggest.
 */
export function useStoreSearchSuggest() {
    const suggestions = ref({
        products: [],
        brands: [],
        categories: [],
        terms: [],
    });
    const loading = ref(false);
    const open = ref(false);
    const activeIndex = ref(-1);

    let debounceTimer = null;
    let abortController = null;

    function flattenItems(data) {
        const items = [];
        for (const p of data.products ?? []) {
            items.push({ type: 'product', ...p });
        }
        for (const b of data.brands ?? []) {
            items.push({ type: 'brand', ...b });
        }
        for (const c of data.categories ?? []) {
            items.push({ type: 'category', ...c });
        }
        for (const t of data.terms ?? []) {
            items.push({ type: 'term', label: t });
        }
        return items;
    }

    async function fetchSuggestions(query) {
        if (abortController) {
            abortController.abort();
        }
        abortController = new AbortController();
        loading.value = true;

        try {
            const url = `${route('store.search.suggest')}?q=${encodeURIComponent(query)}`;
            const res = await fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: abortController.signal,
            });

            if (!res.ok) {
                suggestions.value = { products: [], brands: [], categories: [], terms: [] };
                return;
            }

            const data = await res.json();
            suggestions.value = {
                products: data.products ?? [],
                brands: data.brands ?? [],
                categories: data.categories ?? [],
                terms: data.terms ?? [],
            };
            open.value = flattenItems(suggestions.value).length > 0;
            activeIndex.value = -1;
        } catch (err) {
            if (err?.name !== 'AbortError') {
                suggestions.value = { products: [], brands: [], categories: [], terms: [] };
            }
        } finally {
            loading.value = false;
        }
    }

    function scheduleSuggest(query, debounceMs = 300, minLength = 2) {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        const trimmed = (query ?? '').trim();
        if (trimmed.length < minLength) {
            suggestions.value = { products: [], brands: [], categories: [], terms: [] };
            open.value = false;
            activeIndex.value = -1;
            return;
        }

        debounceTimer = setTimeout(() => {
            debounceTimer = null;
            fetchSuggestions(trimmed);
        }, debounceMs);
    }

    function close() {
        open.value = false;
        activeIndex.value = -1;
    }

    function moveActive(delta, items) {
        if (!items.length) {
            activeIndex.value = -1;
            return;
        }
        const next = activeIndex.value + delta;
        if (next < 0) {
            activeIndex.value = items.length - 1;
        } else if (next >= items.length) {
            activeIndex.value = 0;
        } else {
            activeIndex.value = next;
        }
    }

    onBeforeUnmount(() => {
        if (debounceTimer) clearTimeout(debounceTimer);
        if (abortController) abortController.abort();
    });

    return {
        suggestions,
        loading,
        open,
        activeIndex,
        scheduleSuggest,
        close,
        moveActive,
        flattenItems,
    };
}
