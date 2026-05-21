import { computed, unref } from 'vue';

/**
 * @param {unknown} value
 */
export function isCategoryActive(value) {
    if (value === false || value === 0 || value === '0') {
        return false;
    }
    return true;
}

/**
 * @param {string} slug
 */
export function categoryStoreHref(slug) {
    const s = String(slug ?? '').trim();
    if (!s) {
        try {
            return route('store.shop');
        } catch {
            return '/shop';
        }
    }
    try {
        return route('store.category', s);
    } catch {
        return `/c/${s}`;
    }
}

/**
 * @param {unknown} raw
 * @returns {Array<Record<string, unknown>>}
 */
export function normalizeCategoryList(raw) {
    if (Array.isArray(raw)) {
        return raw;
    }
    if (raw && typeof raw === 'object' && Array.isArray(raw.data)) {
        return raw.data;
    }
    return [];
}

/**
 * @param {unknown} raw
 * @returns {Array<Record<string, unknown>>}
 */
export function normalizeCategoryChildren(raw) {
    if (Array.isArray(raw)) {
        return raw;
    }
    if (raw && typeof raw === 'object') {
        if (Array.isArray(raw.data)) {
            return raw.data;
        }
        return Object.values(raw).filter((v) => v && typeof v === 'object' && 'id' in v);
    }
    return [];
}

/**
 * @param {Record<string, unknown>} raw
 */
export function mapCategoryNode(raw) {
    const slug = String(raw.slug ?? '').trim();
    const name = String(raw.name ?? '').trim();

    return {
        id: raw.id,
        name,
        slug,
        description: String(raw.meta_description ?? '').trim(),
        imageUrl: raw.og_image_url ? String(raw.og_image_url) : null,
        href: categoryStoreHref(slug),
        sortOrder: Number(raw.sort_order ?? 0),
    };
}

/**
 * Active root categories with active children, ordered by admin sort_order.
 *
 * @param {import('vue').MaybeRefOrGetter<Array<Record<string, unknown>>>} categoriesRef
 */
export function useCategoryBrowser(categoriesRef) {
    const parents = computed(() => {
        try {
            const list = normalizeCategoryList(unref(categoriesRef));

            return list
                .filter((c) => c?.slug && isCategoryActive(c.is_active))
                .slice()
                .sort((a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0))
                .map((parent) => {
                    const node = mapCategoryNode(parent);
                    const children = normalizeCategoryChildren(parent.children)
                        .filter((ch) => ch?.slug && isCategoryActive(ch.is_active))
                    .slice()
                    .sort((a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0))
                    .map((ch) => mapCategoryNode(ch));

                    return { ...node, children };
                })
                .filter((p) => p.slug);
        } catch {
            return [];
        }
    });

    const hasContent = computed(() => parents.value.length > 0);

    return { parents, hasContent };
}
